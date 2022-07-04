# Stripe Module

## Быстрый старт

**Доступ к защищенным репозиториям**

Скопировать файл `.npmrc` и указать в нем [токен доступа](https://gitlab.retailcrm.tech/-/profile/personal_access_tokens) с правами `api` + `read_repository`

```bash
$ cp .npmrc.dist .npmrc
```

**Запуск рабочего окружения**

```bash
$ make start            # build and run docker-containers
$ make deps             # install php and js dependencies
$ make create_database  # force recreate new database
$ make assets           # install symfony assets
$ make js_routing       # dump symfony routes for vue.js
$ make encore           # build js files for vue.js
```


В дельнейшем, запуск и остановку контейнеров можно осуществить командами:

```bash
$ make start
$ make stop
```

## Тестирование

Для локального тестирования нужно сначала создать тестовую бд

```bash
$ make setup_test
```

Запуск локальных тестов

```bash
$ make check_cs
$ make run_test
```
