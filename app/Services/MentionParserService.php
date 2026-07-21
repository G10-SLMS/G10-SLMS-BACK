<?php

namespace App\Services;

use App\Models\User;

class MentionParserService
{
    /**
     * Extract unique mentioned user IDs from a text body.
     *
     * Handles are matched case-insensitively and resolved against:
     * - username, when the application has one
     * - email local-part
     * - normalized full name
     * - normalized name tokens, so "System Admin" can still match @admin
     */
    public function extractUserIds(string $content): array
    {
        $handles = $this->extractHandles($content);

        if ($handles === []) {
            return [];
        }

        $exactIndex = [];
        $fallbackIndex = [];

        foreach (User::query()->get() as $user) {
            foreach ($this->exactAliasesFor($user) as $alias) {
                $exactIndex[$alias] ??= $user->id;
            }

            foreach ($this->fallbackAliasesFor($user) as $alias) {
                $fallbackIndex[$alias] ??= $user->id;
            }
        }

        $mentionedUserIds = [];

        foreach ($handles as $handle) {
            $userId = $exactIndex[$handle] ?? $fallbackIndex[$handle] ?? null;

            if ($userId !== null && ! in_array($userId, $mentionedUserIds, true)) {
                $mentionedUserIds[] = $userId;
            }
        }

        return $mentionedUserIds;
    }

    /**
     * @return array<int, string>
     */
    protected function extractHandles(string $content): array
    {
        preg_match_all('/(?<![\w])@([A-Za-z0-9_]+)/', $content, $matches);

        $handles = array_map(
            fn (string $handle) => $this->normalizeHandle($handle),
            $matches[1] ?? []
        );

        $handles = array_values(array_filter($handles, fn (string $handle) => $handle !== ''));

        return array_values(array_unique($handles));
    }

    /**
     * @return array<int, string>
     */
    protected function exactAliasesFor(User $user): array
    {
        $aliases = [];

        foreach ([
            $user->username ?? null,
            $this->emailLocalPart($user->email),
            $user->name,
            (string) ($user->student_id ?? ''),
        ] as $value) {
            $alias = $this->normalizeHandle((string) $value);

            if ($alias !== '') {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return array<int, string>
     */
    protected function fallbackAliasesFor(User $user): array
    {
        $aliases = [];

        foreach ([
            $user->name,
            $this->emailLocalPart($user->email),
        ] as $value) {
            foreach ($this->splitIntoTokens((string) $value) as $token) {
                $alias = $this->normalizeHandle($token);

                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return array<int, string>
     */
    protected function splitIntoTokens(string $value): array
    {
        return preg_split('/[^A-Za-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    protected function emailLocalPart(?string $email): ?string
    {
        if (! is_string($email) || $email === '') {
            return null;
        }

        $parts = explode('@', $email, 2);

        return $parts[0] ?? null;
    }

    protected function normalizeHandle(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '';

        return strtolower($value);
    }
}
