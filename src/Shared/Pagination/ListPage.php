<?php

namespace App\Shared\Pagination;

use Doctrine\ORM\Tools\Pagination\Window;
use Symfony\Component\HttpFoundation\Request;

// Long lists show 50 rows, then the next 50 arrive while scrolling (infinite_scroll_controller.js
// asks for ?page=N with the X-Next-Rows header, and gets the rows alone, without the page around).
final class ListPage
{
    public const SIZE = 50;

    // The page asked for, the first one otherwise.
    public static function window(Request $request): Window
    {
        return Window::fromPageNumberAndSize(max(1, $request->query->getInt('page', 1)), self::SIZE);
    }

    // A request for the next rows only, not for a whole page.
    public static function isNextRows(Request $request): bool
    {
        return $request->headers->has('X-Next-Rows');
    }
}
