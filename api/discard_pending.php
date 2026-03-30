<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();
csrf_check();

clear_pending_composite();

json_response(['success' => true]);
