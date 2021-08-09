# Stripe Module

## Быстрый старт

**Доступ к защищенным репозиториям**

Создать в корне файл `auth.json` с [токеном доступа](https://gitlab.retailcrm.tech/-/profile/personal_access_tokens) с правами `api` + `read_repository`

**Запуск рабочего окружения**

```bash
$ make start
$ make deps
$ make create_database
$ make assets
$ make js_routing
$ make encore
```


В дельнейшем запуск можно осуществить командой

```bash
$ make start
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
