<?php

namespace Mita\UranusHttpServer\Console;

class Setup
{
    public static function createUranusFile()
    {
        $filePath = getcwd() . '/uranus';

        if (!file_exists($filePath)) {
            $content = <<<EOD
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

\$command = implode(' ', array_slice(\$argv, 1));
\$uranusCommand = __DIR__ . '/vendor/bin/uranus ' . \$command;

\$descriptorspec = array(
   0 => STDIN,
   1 => STDOUT,
   2 => STDERR
);

\$process = proc_open(\$uranusCommand, \$descriptorspec, \$pipes);

if (is_resource(\$process)) {
    proc_close(\$process);
}
EOD;
            file_put_contents($filePath, $content);
            chmod($filePath, 0755);
            echo "Uranus CLI file created successfully.\n";
        } else {
            echo "Uranus CLI file already exists.\n";
        }
    }
}
