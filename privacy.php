<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Политика конфиденциальности — MasterHacks</title>
    <meta name="description" content="Политика конфиденциальности MasterHacks — как мы обрабатываем ваши данные.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta property="og:title" content="Политика конфиденциальности — MasterHacks">
    <meta property="og:description" content="Как MasterHacks обрабатывает ваши данные.">
    <meta property="og:image" content="https://masterhacks.ru/gk.png">
    <meta property="og:url" content="https://masterhacks.ru/privacy.php">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0d0d0d;
            --surface: #161616;
            --text: #e0e0e0;
            --muted: #999;
            --accent: #00d4aa;
            --border: #222;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.7;
            min-height: 100vh;
        }
        nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(13,13,13,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 12px 20px;
            display: flex; align-items: center; gap: 12px;
        }
        nav a { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 15px; }
        nav a:hover { opacity: 0.8; }
        nav .logo { font-size: 18px; font-weight: 700; color: var(--text); margin-right: auto; }
        main {
            max-width: 720px; margin: 0 auto; padding: 40px 20px 80px;
        }
        h1 { font-size: 28px; margin-bottom: 8px; color: #fff; }
        h2 { font-size: 20px; margin: 32px 0 12px; color: #fff; border-bottom: 1px solid var(--border); padding-bottom: 6px; }
        p, li { margin-bottom: 12px; color: var(--text); }
        ul { padding-left: 20px; margin-bottom: 16px; }
        .updated { color: var(--muted); font-size: 13px; margin-bottom: 24px; }
        footer {
            text-align: center; padding: 24px; color: var(--muted); font-size: 13px;
            border-top: 1px solid var(--border);
        }
        footer a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
<nav>
    <a href="/" class="logo"><i class="fas fa-bolt"></i> MasterHacks</a>
    <a href="/terms.php">Условия</a>
</nav>
<main>
    <h1>Политика конфиденциальности</h1>
    <p class="updated">Последнее обновление: 5 июня 2026 г.</p>

    <p>MasterHacks («мы», «наш» или «сервис») с уважением относится к вашей конфиденциальности. В этом документе объясняется, какие данные мы собираем, как мы их используем и какие права у вас есть.</p>

    <h2>1. Какие данные мы собираем</h2>
    <ul>
        <li><strong>Данные Telegram-аккаунта:</strong> при входе через Telegram мы получаем ваш Telegram ID, имя пользователя и (если доступно) аватар. Это минимальный набор, необходимый для идентификации.</li>
        <li><strong>Загружаемый контент:</strong> видео и изображения, которые вы отправляете через бота для публикации на платформе.</li>
        <li><strong>Активность на сайте:</strong> просмотры видео, лайки, комментарии, закладки — для функционирования сервиса и отображения статистики.</li>
        <li><strong>Технические данные:</strong> IP-адрес, user-agent браузера, cookie сессии (PHPSESSID) — стандартные данные для работы любого веб-сайта.</li>
    </ul>

    <h2>2. Как мы используем данные</h2>
    <ul>
        <li>Для входа в ваш аккаунт и отображения ваших видео.</li>
        <li>Для публикации одобренных видео в ленте.</li>
        <li>Для подсчёта просмотров, лайков и комментариев.</li>
        <li>Для отправки уведомлений через Telegram-бота (только если вы подписались на рассылку).</li>
    </ul>

    <h2>3. Хранение данных</h2>
    <p>Все данные хранятся на защищённом сервере в зашифрованной базе данных. Мы не передаём ваши данные третьим лицам и не используем их для рекламных целей.</p>

    <h2>4. Реферальная система</h2>
    <p>При создании реферальной ссылки генерируется уникальный код, связанный с вашим аккаунтом. Мы считаем переходы по вашей ссылке и показываем эту статистику в боте.</p>

    <h2>5. Cookie</h2>
    <p>Мы используем только сессионные cookie (PHPSESSID) для поддержания входа. Никаких отслеживающих или рекламных cookie.</p>

    <h2>6. Удаление данных</h2>
    <p>Вы можете удалить любое своё видео через личный кабинет или запросить полное удаление аккаунта, написав нам через Telegram-бота <a href="https://t.me/mastershacks_bot">@mastershacks_bot</a>.</p>

    <h2>7. Связь с нами</h2>
    <p>По любым вопросам: <a href="https://t.me/mastershacks_bot">@mastershacks_bot</a> в Telegram.</p>
</main>
<footer>
    <a href="/">MasterHacks</a> · <a href="/privacy.php">Конфиденциальность</a> · <a href="/terms.php">Условия</a>
</footer>
</body>
</html>
