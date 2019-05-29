<?php
namespace Civi\Cv\Util;

class Datasource {
  private static $attribute_names = array(
    'database',
    'driver',
    'host',
    'password',
    'port',
    'username',
  );
  private static $cividsn_to_settings_name = array(
    'database' => 'database',
    'dbsyntax' => 'driver',
    'hostspec' => 'host',
    'password' => 'password',
    'port' => 'port',
    'username' => 'username',
  );
  private static $settings_to_doctrine_options = array(
    'database' => 'dbname',
    'driver' => 'driver',
    'host' => 'host',
    'password' => 'password',
    'port' => 'port',
    'username' => 'user',
  );
  private static $settings_to_pdo_options = array(
    'host' => 'host',
    'port' => 'port',
    'database' => 'dbname',
    'socket_path' => 'unix_socket',
  );

  private $database;
  private $driver;
  private $host;
  private $password;
  private $port;
  private $socket_path;
  private $username;

  public function __construct($options = NULL) {
    if ($options !== NULL) {
      if (isset($options['civi_dsn'])) {
        $this->loadFromCiviDSN($options['civi_dsn']);
      }
      elseif ($options['settings_array']) {
        $this->loadFromSettingsArray($options['settings_array']);
      }
      else {
        // var_dump(array('o' => $options));
        throw new \InvalidArgumentException("The options parameter needs to be blank if you want to load from CIVICRM_DSN, or it can be an array with key 'civi_dsn' that is a CiviCRM formatted DSN string, or it can be an array with key 'settings_array' than points to another array of database settings.");
      }
    }
  }

  public function loadFromCiviDSN($civi_dsn) {
    // As long as we use this in a post-boot environment, so it's OK to call \DB...
    $parsed_dsn = \DB::parseDSN($civi_dsn);
    foreach (static::$cividsn_to_settings_name as $key => $value) {
      if (array_key_exists($key, $parsed_dsn)) {
        $this->$value = $parsed_dsn[$key];
      }
    }
    $this->updateHost();
  }

  public function loadFromSettingsArray($settings_array) {
    foreach ($settings_array as $key => $value) {
      $this->$key = $value;
    }
    $this->updateHost();
  }

  /**
   * @return PDO
   */
  public function createPDO() {
    $pdo = new \PDO($this->toPDODSN(), $this->username, $this->password);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $pdo;
  }

  /**
   * @return bool
   */
  public function isValid() {
    try {
      $dbh = $this->createPDO();
      foreach ($dbh->query('SELECT 99 as value') as $row) {
        if ($row['value'] == 99) {
          return TRUE;
        }
      }
    }
    catch (\PDOException $e) {
    }
    $dbh = NULL;
    return FALSE;
  }

  public function toCiviDSN() {
    $civi_dsn = "{$this->driver}://{$this->username}:{$this->password}@{$this->host}";
    if ($this->port !== NULL) {
      $civi_dsn = "$civi_dsn:{$this->port}";
    }
    $civi_dsn = "$civi_dsn/{$this->database}?new_link=true";
    return $civi_dsn;
  }

  public function toDoctrineArray() {
    $result = array();
    foreach (self::$settings_to_doctrine_options as $key => $value) {
      $result[$value] = $this->$key;
    }
    $result['driver'] = "pdo_{$result['driver']}";
    return $result;
  }

  public function toDrupalDSN() {
    $drupal_dsn = "{$this->driver}://{$this->username}:{$this->password}@{$this->host}";
    if ($this->port !== NULL) {
      $drupal_dsn = "$drupal_dsn:{$this->port}";
    }
    $drupal_dsn = "$drupal_dsn/{$this->database}";
    return $drupal_dsn;
  }

  /**
   * @param string $tmpDir
   *   A directory in which we can create temporary files.
   * @return string
   */
  public function toMySQLArguments($tmpDir) {
    $data = "[client]\n";
    $data .= "host={$this->host}\n";
    $data .= "user={$this->username}\n";
    $data .= "password={$this->password}\n";
    if ($this->port != NULL) {
      $data .= "port={$this->port}\n";
    }

    $file = $tmpDir . '/my.cnf-' . md5($data);
    if (!file_exists($file)) {
      if (!file_put_contents($file, $data)) {
        throw new \RuntimeException("Failed to create temporary my.cnf connection file.");
      }
    }

    $args = "--defaults-file=" . escapeshellarg($file);
    $args .= " {$this->database}";
    return $args;
  }

  public function toPHPArrayString() {
    $result = "array(\n";
    foreach (static::$attribute_names as $attribute_name) {
      $result .= "  '$attribute_name' => '{$this->$attribute_name}',\n";
    }
    $result .= ")";
    return $result;
  }

  public function toPDODSN($options = array()) {
    $pdo_dsn = "{$this->driver}:";
    $pdo_dsn_options = array();
    $settings_to_pdo_options = static::$settings_to_pdo_options;
    if (isset($options['no_database']) && $options['no_database']) {
      unset($settings_to_pdo_options['database']);
    }
    foreach ($settings_to_pdo_options as $settings_name => $pdo_name) {
      if ($this->$settings_name !== NULL) {
        $pdo_dsn_options[] = "{$pdo_name}={$this->$settings_name}";
      }
    }
    $pdo_dsn .= implode(';', $pdo_dsn_options);
    return $pdo_dsn;
  }

  /**
   * FIXME: pg, mysql-specific
   */
  public function updateHost() {
    /*
     * If you use localhost for the host, the MySQL client library will
     * use a unix socket to connect to the server and ignore the port,
     * so if someone is not going to use the default port, let's
     * assume they don't want to use the unix socket.
     */
    if ($this->port != NULL && $this->host == 'localhost') {
      $this->host = '127.0.0.1';
    }
  }

  public function setDatabase($database) {
    $this->database = $database;
  }

  public function getDatabase() {
    return $this->database;
  }

  public function setDriver($driver) {
    $this->driver = $driver;
  }

  public function getDriver() {
    return $this->driver;
  }

  public function setHost($host) {
    $this->host = $host;
  }

  public function getHost() {
    return $this->host;
  }

  public function setPassword($password) {
    $this->password = $password;
  }

  public function getPassword() {
    return $this->password;
  }

  public function setPort($port) {
    $this->port = $port;
  }

  public function getPort() {
    return $this->port;
  }

  public function setUsername($username) {
    $this->username = $username;
  }

  public function getUsername() {
    return $this->username;
  }

  public function setSocketPath($socket_path) {
    $this->socket_path = $socket_path;
  }

  public function getSocketPath() {
    return $this->socket_path;
  }

}
