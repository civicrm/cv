<?php
print "Count: $argc\n";
if (!$argc) {
  print "No arguments passed.\n";
}
else {
  foreach ($argv as $key => $val) {
    print "$key: $val\n";
  }
}
