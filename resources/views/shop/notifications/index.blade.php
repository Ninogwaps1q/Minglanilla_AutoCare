<x-layouts.app title="Notifications">
    <x-notification-center :notifications="$notifications" :unread-count="$unreadNotificationCount" :read-count="$readNotificationCount" :filter="$filter"
        index-route="shop.notifications.index" bulk-route="shop.notifications.bulk-update" read-route="shop.notifications.update" open-route="shop.notifications.open"
        description="Keep up with bookings, payments, and customer messages." />
</x-layouts.app>
