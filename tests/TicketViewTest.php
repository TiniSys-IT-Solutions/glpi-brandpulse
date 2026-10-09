<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\Config;
use GlpiPlugin\Brandpulse\CounterService;
use GlpiPlugin\Brandpulse\TicketView;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TicketViewTest extends TestCase
{
    protected function tearDown(): void
    {
        \Ticket::$readAllowed = true;
        unset($GLOBALS['CFG_GLPI']);
    }

    public function testExistingCountersKeepNativePreferencesByDefault(): void
    {
        self::assertTrue(Config::normalizeCounters(Config::defaultCounters())[0]['view']['use_glpi']);
        self::assertSame([], TicketView::parameters([], 'Ticket'));
    }

    public function testSettingsNormalizeAndKeepDistinctColumnOrder(): void
    {
        $view = TicketView::normalize([
            'use_glpi' => false, 'columns' => [7, '12', 7, -1, 'invalid', ['bad']],
            'sort' => '19', 'order' => 'drop table',
        ]);
        self::assertSame([7, 12], $view['columns']);
        self::assertSame(19, $view['sort']);
        self::assertSame('DESC', $view['order']);
    }

    public function testParametersRemoveFixedHiddenUnknownAndNonSortableFields(): void
    {
        self::assertSame(['forcetoview' => [7, 12]], TicketView::parameters([
            'use_glpi' => false, 'columns' => [7, 2, 12, 999, 1000], 'sort' => 21,
        ], 'Ticket'));
        self::assertSame([], TicketView::parameters(['use_glpi' => false], 'Computer'));
        \Ticket::$readAllowed = false;
        self::assertSame([], TicketView::parameters(['use_glpi' => false], 'Ticket'));
    }

    public function testLinkUsesViewWithoutChangingSavedFiltersAndSupportsBothDirections(): void
    {
        $GLOBALS['CFG_GLPI']['root_doc'] = '/glpi';
        $service = new CounterService(10);
        $searchUrl = new ReflectionMethod($service, 'searchUrl');
        $criteria = [['field' => 7, 'searchtype' => 'equals', 'value' => 15]];
        foreach (['ASC', 'DESC'] as $direction) {
            $url = $searchUrl->invoke($service, 'Ticket', [
                'criteria' => $criteria, 'sort' => [12], 'order' => ['DESC'],
            ], ['use_glpi' => false, 'columns' => [19, 7, 12], 'sort' => 19, 'order' => $direction]);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('/glpi/front/ticket.php', parse_url($url, PHP_URL_PATH));
            self::assertSame(['19', '7', '12'], $query['forcetoview']);
            self::assertSame(['19'], $query['sort']);
            self::assertSame([$direction], $query['order']);
            self::assertSame('15', $query['criteria'][0]['value']);
            self::assertSame('reset', $query['reset']);
        }
    }

    public function testNativePreferencesPreserveSavedSortAndEmptyViewKeepsFixedColumns(): void
    {
        $GLOBALS['CFG_GLPI']['root_doc'] = '/glpi';
        $method = new ReflectionMethod(CounterService::class, 'searchUrl');
        $url = $method->invoke(new CounterService(10), 'Ticket', ['sort' => [7], 'order' => ['ASC']]);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame(['7'], $query['sort']);
        self::assertSame(['ASC'], $query['order']);
        self::assertArrayNotHasKey('forcetoview', $query);
        $url = $method->invoke(new CounterService(10), 'Ticket', ['criteria' => []], ['use_glpi' => false]);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame(['0'], $query['forcetoview']);
    }

    public function testViewSurvivesCatalogueNormalizationAndIsIndependentForEachCounter(): void
    {
        $catalog = Config::defaultCounters();
        $catalog[0]['view'] = ['use_glpi' => false, 'columns' => [12, 7], 'sort' => 7, 'order' => 'ASC'];
        $catalog[1]['view'] = ['use_glpi' => false, 'columns' => [19, 12], 'sort' => 19, 'order' => 'DESC'];
        $normalized = Config::normalizeCounters($catalog);
        self::assertSame([12, 7], $normalized[0]['view']['columns']);
        self::assertSame([19, 12], $normalized[1]['view']['columns']);
        self::assertSame($normalized, Config::normalizeCounters($normalized));
    }
}
