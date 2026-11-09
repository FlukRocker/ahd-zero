<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Chat carries no links at all, internal ones included: a scheme, a "www."
 * host, or a bare name.tld. TLDs must be 2–24 letters, so "ep.12", "3.5" and
 * Thai text are not flagged. Run on ChatText::clean() output, which has already
 * folded fullwidth forms and removed zero-width splitters.
 */
class NoLinks implements ValidationRule
{
    /** Common and commonly abused TLDs. A list, so "ok.thanks" is not a link but "x.com" is. */
    private const TLDS = 'com|net|org|info|biz|edu|gov|io|co|me|tv|cc|to|ly|gl|gg|app|dev|xyz|site|online|top|club|shop|live|link|click|store|tech|website|space|fun|icu|vip|pro|cloud|win|bid|loan|work|world|today|news|asia|mobi|name|pw|ws|tk|ml|ga|cf|gq|ru|cn|th|jp|kr|us|uk|de|fr|in|br|id|vn|my|sg|ph|tw|hk|nl|it|es|pl|ua|tr|ir|ae|za|au|ca|eu|ch|se|no|fi|dk|be|at|cz|ro|gr|pt|ar|mx|cl|co\.th|in\.th';

    private const PATTERN = '~(?:[a-z][a-z0-9+.\-]{1,15}:(?://|[a-z0-9]*@)|\bwww\d?\.|(?<![a-z0-9])[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9][a-z0-9\-]*)*\.(?:'.self::TLDS.')(?![a-z0-9]))~iu';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Also catch "javascript:" / "data:" with nothing after the colon-scheme.
        if (preg_match(self::PATTERN, $value) === 1 || preg_match('~\b(?:javascript|vbscript|data)\s*:~i', $value) === 1) {
            $fail('ห้ามแนบลิงก์ในแชท');
        }
    }
}
