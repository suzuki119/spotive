<?php

/**
 * pages/organizer/dashboard.php
 * 以前の主催者ページ。主催者ページは pages/administrators/administrators.php に移したので、
 * ブックマークや古いリンクから開かれたときのために転送だけする。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/support.php';

redirect(url('pages/administrators/administrators.php'));
