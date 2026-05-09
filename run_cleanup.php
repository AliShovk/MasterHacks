<?php
// Скрипт для немедленной очистки мертвых записей
require_once 'telegram_bot.php';

echo "Запускаю очистку мертвых записей...\n";
cleanupDeadPosts();
echo "Очистка завершена!\n";
?>
