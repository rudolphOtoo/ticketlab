<?php

declare(strict_types=1);

// /api/run-scheduler only (vercel.json routes it here). Its own Vercel function so its maxDuration can differ from
// the pages' (config/scheduler.php: function_max_duration). Same bootstrap as every other request.
require __DIR__ . '/index.php';
