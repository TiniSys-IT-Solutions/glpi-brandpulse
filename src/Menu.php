<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

use CommonGLPI;
use Session;

class Menu extends CommonGLPI
{
    public static string $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return __('GLPI BrandPulse', 'brandpulse');
    }

    public static function getMenuName($nb = 0): string
    {
        return __('BrandPulse', 'brandpulse');
    }

    public static function getMenuContent(): array|false
    {
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }

        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/brandpulse/front/config.php',
            'icon'  => self::getIcon(),
        ];
    }

    public static function getIcon(): string
    {
        return 'ti ti-brush';
    }
}
