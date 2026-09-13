<?php
// Keep the load balancer check independent from RDS. Database failures should
// be handled by application pages, but should not remove a booting web server
// from the target group.
header('Content-Type: text/plain');
echo 'OK';
