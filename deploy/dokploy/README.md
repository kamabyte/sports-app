# Развёртывание в Dokploy

Приложение разворачивается как стек Compose (источник Raw) из файла
[`compose.yml`](compose.yml).

    push ──► GitHub Actions ──► ghcr.io/kamabyte/sports-app:{sha-…, latest}
                                          │
                   Dokploy → стек sports → Deploy ──► web, scheduler

| Сервис      | Что делает                                                                                                       |
| ----------- | ---------------------------------------------------------------------------------------------------------------- |
| `web`       | Laravel и nginx; при запуске — миграции, `storage:link`, кеши конфигурации                                       |
| `scheduler` | `schedule:work`: `guide:import` каждые 30 минут, `catalog:import` раз в час, `catalog:discover` по понедельникам |

Порт наружу не публикуется: приложение открывается через домен стека
(вкладка Domains, порт 8080).

## Dispatcharr из контейнера

Адрес `iptv.home.internal` закрыт middleware `nas-admin-only`, и запрос
из контейнера получает `403`. Поэтому адресов два:

- `DISPATCHARR_URL` (`http://iptv.home.internal`): из него строятся ссылки
  на потоки для браузера;
- `DISPATCHARR_API_URL` (`http://iptv-wmfz8f-dispatcharr-1:9191`): по нему
  приложение обращается к API и плейлисту через сеть `dokploy-network`.

Имя контейнера Dispatcharr меняется, только если стек `iptv` создать заново.

## Подготовка сервера

1. Каталоги данных:

       sudo install -d -o 33 -g 33 /srv/sports /srv/sports/state \
           /srv/sports/state/database /srv/sports/state/app \
           /srv/sports/state/app/public /srv/sports/state/app/private

2. Стек `sports` в проекте `sports` (Compose, Raw). При первой выкатке скрипт
   ниже его не создаёт: создайте стек в панели или через `homelab/apps/deploy.sh`.

3. Во вкладке Environment стека задайте переменные:

    | Переменная             | Значение                                             |
    | ---------------------- | ---------------------------------------------------- |
    | `APP_KEY`              | `php artisan key:generate --show`                    |
    | `APP_URL`              | `http://sports.home.internal`                        |
    | `DISPATCHARR_API_URL`  | `http://iptv-wmfz8f-dispatcharr-1:9191`              |
    | `DISPATCHARR_USERNAME` | логин Xtream (тот же, что в локальном `.env`)        |
    | `DISPATCHARR_PASSWORD` | пароль Xtream                                        |
    | `FLASHSCORE_ENABLED`   | `true`, если владелец включил источник каталога      |
    | `IMAGE_TAG`            | тег образа `sha-…`; устанавливается скриптом выкатки |

4. Во вкладке Domains: Host `sports.home.internal`, Path `/`, порт 8080,
   HTTPS выключен.

5. Первый запуск на пустой базе (на сервере, в контейнере `web`):

       php artisan sports:user you@example.com --name="Имя"
       php artisan catalog:discover
       php artisan catalog:import
       php artisan guide:import

## Выкатка

Скрипт [`deploy.sh`](deploy.sh) работает так же, как в jellyfin-web. Он
дожидается сборки образа коммита в GitHub Actions, загружает `compose.yml`
в стек, устанавливает `IMAGE_TAG=sha-<коммит>`, запускает Deploy и проверяет
ответ `/up`.

    git push && deploy/dokploy/deploy.sh   # origin/main на все установки
    deploy/dokploy/deploy.sh <коммит>      # закрепить или откатить на <коммит>
    deploy/dokploy/deploy.sh -t home       # только одна установка

Установка описывается файлом `deploy/dokploy/targets/<имя>.env`; каталог
исключён из репозитория:

    DOKPLOY_URL=http://dokploy.home.internal
    KEYCHAIN=dokploy-api                   # общий ключ API в связке ключей macOS

Требуются `gh` (с авторизацией), `jq` и `curl`.

## Данные

| Путь на хосте                | Назначение                                                 |
| ---------------------------- | ---------------------------------------------------------- |
| `/srv/sports/state/database` | база SQLite: каталог, каналы, показы, пользователи         |
| `/srv/sports/state/app`      | `storage/app`: телегиды (~130 МБ), кеш Flashscore, эмблемы |

Телегиды и кеш страниц восстанавливаются сами при следующем импорте. Каталог
и учётные записи хранятся только в базе.
