<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Testing;

use PHPUnit\Framework\AssertionFailedError;
use Tagwerk\PluginApi\Dashboard\DataWidget;
use Tagwerk\PluginApi\Dashboard\HandlesActions;
use Tagwerk\PluginApi\Dashboard\HasSettings;
use Tagwerk\PluginApi\Dashboard\WidgetContent;
use Tagwerk\PluginApi\Dashboard\WidgetContext;
use Tagwerk\PluginApi\Languages\Coverage;
use Tagwerk\PluginApi\Languages\LanguagePack;
use Tagwerk\PluginApi\Languages\Reference;
use Tagwerk\PluginApi\Themes\Stylesheet;
use Tagwerk\PluginApi\Themes\Theme;
use Throwable;

/**
 * Checks a plugin can run in its own repository, without a Tagwerk
 * checkout: `use PluginAssertions;` in a PHPUnit `TestCase`.
 *
 * They check what Tagwerk would otherwise only find on a wall: a tile that
 * breaks on the empty settings it starts with, a form with a key twice,
 * content JSON cannot carry, a button name anyone may send unguarded.
 */
trait PluginAssertions
{
    private const KEY = '#^[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*$#';

    /** A household as a tile sees it. */
    protected function widgetContext(
        int $householdId = 1,
        string $timezone = 'Europe/Vienna',
        string $locale = 'de',
        array $areas = [],
    ): WidgetContext {
        return new WidgetContext($householdId, $timezone, $locale, $areas);
    }

    /**
     * Label and description, a sound settings form, content for empty
     * settings and for $settings, and buttons that guard what they do not
     * know.
     *
     * @param  array<string, mixed>  $settings  what the form would store, secrets in plain
     */
    protected function assertWidgetWorks(DataWidget $widget, array $settings = [], ?WidgetContext $context = null): void
    {
        $context ??= $this->widgetContext();

        $this->assertNotSame('', trim($widget->label()), 'A widget needs a label for the picker.');

        if ($widget instanceof HasSettings) {
            $keys = [];
            foreach ($widget->settings() as $field) {
                $this->assertNotSame('', $field->key, 'A setting needs a key.');
                $this->assertNotContains($field->key, $keys, "The setting {$field->key} is declared twice.");
                if ($field->kind === 'select') {
                    $this->assertNotSame([], $field->options, "The select {$field->key} offers nothing.");
                }
                $keys[] = $field->key;
            }
        }

        $empty = $this->contentOf(fn (): WidgetContent => $widget->content($context, []), 'with empty settings — a tile from the picker starts without any');
        $this->assertCarried($empty);

        if ($settings !== []) {
            $this->assertCarried($this->contentOf(fn (): WidgetContent => $widget->content($context, $settings), 'with the given settings'));
        }

        if ($widget instanceof HandlesActions && ! $widget->guarded('zz-unknown-action')) {
            $this->contentOf(function () use ($widget, $context, $settings): never {
                $widget->act($context, $settings, 'zz-unknown-action');
                $this->fail('An unknown action ran unguarded. act() receives any well-formed name: guard it or refuse it.');
            }, 'for an unknown action', refusalIsFine: true);
        }
    }

    /**
     * A pack Tagwerk accepts, with every reference text translated and the
     * same placeholders. What fails here would show in English on a wall —
     * the message lists each text, so a new Tagwerk version tells the
     * translator what it added.
     */
    protected function assertLanguagePackComplete(LanguagePack $pack): void
    {
        $problem = Coverage::problem($pack);
        $this->assertNull($problem, (string) $problem);

        $coverage = Coverage::of($pack, Reference::load());
        $lines = [
            ...array_map(static fn (string $key): string => "missing: {$key}", $coverage['missing']),
            ...array_map(static fn (string $key): string => "placeholders differ: {$key}", $coverage['broken']),
        ];

        $this->assertSame([], $lines, count($lines)." of {$coverage['total']} texts would show in English:\n".implode("\n", $lines));
    }

    /**
     * A design Tagwerk will show: only its tokens, light and dark, fonts and
     * pictures beside the file, readable text in both themes.
     */
    protected function assertThemeValid(Theme $theme): void
    {
        $problem = Stylesheet::problem($theme);

        $this->assertNull($problem, (string) $problem);
    }

    /** A key a plugin may offer: vendor/name, lower case, not Tagwerk's. */
    protected function assertPluginKey(string $key): void
    {
        $this->assertMatchesRegularExpression(self::KEY, $key, "{$key} must look like vendor/name in lower case.");
        $this->assertStringStartsNotWith('tagwerk/', $key, 'The tagwerk/ prefix is reserved.');
    }

    private function contentOf(callable $call, string $when, bool $refusalIsFine = false): ?WidgetContent
    {
        try {
            return $call();
        } catch (AssertionFailedError $failure) {
            throw $failure;
        } catch (Throwable $error) {
            if (! $refusalIsFine) {
                $this->fail("The widget threw {$when}: ".$error::class.': '.$error->getMessage());
            }

            return null;
        }
    }

    private function assertCarried(?WidgetContent $content): void
    {
        $this->assertNotNull($content);
        $this->assertNotFalse(json_encode($content->toArray()), 'The content cannot travel as JSON: '.json_last_error_msg());
    }
}
