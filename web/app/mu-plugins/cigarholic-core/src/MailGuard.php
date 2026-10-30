<?php

namespace Cigarholic\Core;

use function Env\env;

/**
 * FND-07: outside production, trap outgoing mail unless every recipient is on MAIL_ALLOWLIST.
 *
 * Keeps local and staging copies of the store from emailing real customers. Trapped mail is
 * reported as sent so checkout and account flows behave normally, and is logged instead.
 */
final class MailGuard
{
    public static function register(): void
    {
        if (wp_get_environment_type() === 'production') {
            return;
        }

        add_filter('pre_wp_mail', [self::class, 'filter'], 10, 2);
    }

    /**
     * @param null|bool $return Short-circuit value from an earlier filter.
     * @param array<string, mixed> $atts wp_mail() arguments.
     */
    public static function filter($return, array $atts): ?bool
    {
        if ($return !== null) {
            return $return;
        }

        $allowed = array_filter(array_map(
            static fn (string $address): string => strtolower(trim($address)),
            explode(',', (string) env('MAIL_ALLOWLIST'))
        ));

        $recipients = is_array($atts['to']) ? $atts['to'] : explode(',', (string) $atts['to']);

        foreach ($recipients as $recipient) {
            $address = preg_match('/<([^>]+)>/', $recipient, $match) ? $match[1] : $recipient;

            if (!in_array(strtolower(trim($address)), $allowed, true)) {
                error_log(sprintf(
                    '[cigarholic] MailGuard trapped mail to %s: %s',
                    implode(', ', $recipients),
                    (string) ($atts['subject'] ?? '')
                ));

                return true;
            }
        }

        return null;
    }
}
