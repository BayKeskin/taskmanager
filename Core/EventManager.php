<?php
namespace Core;

/**
 * EventManager - WordPress tarzı Hook & Event Yönetim Motoru
 * Modüller arasındaki bağımsız iletişimi (Loose Coupling) sağlar.
 */
class EventManager
{
    private static array $listeners = [];
    private static array $filters = [];

    /**
     * Bir olaya dinleyici (action listener) kaydeder (WordPress add_action dengi)
     */
    public static function listen(string $event, callable $callback, int $priority = 10): void
    {
        self::$listeners[$event][$priority][] = $callback;
    }

    /**
     * Bir olayı fırlatır ve tüm dinleyicileri çalıştırır (WordPress do_action dengi)
     */
    public static function dispatch(string $event, mixed $payload = null): void
    {
        if (empty(self::$listeners[$event])) {
            return;
        }

        ksort(self::$listeners[$event]);
        foreach (self::$listeners[$event] as $priorityGroup) {
            foreach ($priorityGroup as $callback) {
                call_user_func($callback, $payload);
            }
        }
    }

    /**
     * Bir filtre dinleyicisi kaydeder (WordPress add_filter dengi)
     */
    public static function addFilter(string $filter, callable $callback, int $priority = 10): void
    {
        self::$filters[$filter][$priority][] = $callback;
    }

    /**
     * Değeri kayıtlı filtrelerden geçirir (WordPress apply_filters dengi)
     */
    public static function applyFilters(string $filter, mixed $value, mixed ...$args): mixed
    {
        if (empty(self::$filters[$filter])) {
            return $value;
        }

        ksort(self::$filters[$filter]);
        foreach (self::$filters[$filter] as $priorityGroup) {
            foreach ($priorityGroup as $callback) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }
        return $value;
    }

    /**
     * Kayıtlı dinleyicileri temizler (Testler için)
     */
    public static function clear(): void
    {
        self::$listeners = [];
        self::$filters = [];
    }
}
