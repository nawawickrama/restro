<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A guard against a Blade trap this codebase has walked into twice.
 *
 * Blade lifts raw PHP blocks out with `/(?<!@)@php(.*?)@endphp/s` before
 * compiling anything else. The match is lazy, but it opens at whichever `@php`
 * it reaches first — and the inline `@php($x = 1)` form is spelled the same
 * way while having no `@endphp` of its own. When an inline directive is the one
 * that opens a match, Blade runs from it to the *next* block's `@endphp` and
 * swallows every tag in between into one malformed `<?php(`, taking the page
 * down with a parse error pointing nowhere near the cause.
 *
 * Order is what matters, not mere coexistence: a block that opens and closes
 * before any inline directive is consumed on its own and is perfectly safe.
 * This walks each template the way Blade's scanner does and only complains
 * when an inline directive would actually capture a later `@endphp`.
 */
class BladeSafetyTest extends TestCase
{
    public function test_no_template_lets_an_inline_php_directive_swallow_a_block(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if ($line = $this->swallowedAt($file->getContents())) {
                $path = str_replace(resource_path('views').'/', '', $file->getPathname());
                $offenders[] = "{$path} (line {$line})";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "An inline @php(...) directive will swallow a later @php...@endphp block here:\n  "
            .implode("\n  ", $offenders),
        );
    }

    /** The detector itself, checked against the shape that caused the outage. */
    public function test_the_detector_recognises_the_dangerous_ordering(): void
    {
        $broken = "@php(\$day = \$start)\n@php\n    \$x = 1;\n@endphp\n";
        $safeBlockFirst = "@php\n    \$x = 1;\n@endphp\n@php(\$day = \$start)\n";
        $safeInlineOnly = "@php(\$day = \$start)\n<div>@php(\$n = 2)</div>\n";
        $safeBlockOnly = "@php\n    \$x = 1;\n@endphp\n";

        $this->assertNotNull($this->swallowedAt($broken));
        $this->assertNull($this->swallowedAt($safeBlockFirst));
        $this->assertNull($this->swallowedAt($safeInlineOnly));
        $this->assertNull($this->swallowedAt($safeBlockOnly));
    }

    /**
     * Walks the template the way Blade's raw-block scanner does, returning the
     * line of the first inline directive that would capture an `@endphp`.
     */
    private function swallowedAt(string $template): ?int
    {
        $offset = 0;

        while (preg_match('/(?<!@)@php([ \t]*\()?/', $template, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $isInline = isset($match[1]) && $match[1][1] !== -1;

            $end = strpos($template, '@endphp', $start);

            if ($end === false) {
                // Nothing left to swallow.
                return null;
            }

            if ($isInline) {
                return substr_count(substr($template, 0, $start), "\n") + 1;
            }

            // A block form consumes up to its own @endphp; scanning resumes after.
            $offset = $end + strlen('@endphp');
        }

        return null;
    }
}
