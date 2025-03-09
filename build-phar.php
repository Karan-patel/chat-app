<?php
$phar = new Phar('chat-app.phar');
$phar->buildFromDirectory(__DIR__, '/\.(php)$/');
$phar->setStub("#!/usr/bin/env php\n<?php Phar::mapPhar(); require 'phar://chat-app.phar/public/index.php'; __HALT_COMPILER();");
chmod('chat-app.phar', 0755);
echo "PHAR created: chat-app.phar\n";