# Bitrix Copilot Workspace

Этот репозиторий — рабочая площадка для разработки и сопровождения Bitrix-проектов силами GitHub Copilot и специализированных агентов. Он объединяет требования, архитектуру, тестовые данные, артефакты поставки и инструкции, чтобы можно было запускать новый модуль, собирать релиз и вести документацию без переключения в другие хранилища.

## Роли агентов

- **00-help-assistant** — вспомогательный проводник по Workspace: отвечает на вопросы о структуре проекта, подборе агентов и безопасных изменениях в документации и `.agent.md`.
- **10-requirements-create** — из сырых идей и черновых ТЗ формирует структурированные требования в `inputs/requirements/**`, при необходимости обновляя `inputs/README.md`.
- **10-domain-describe** — описывает предметную область по актуальным требованиям, поддерживает глоссарий, сущности и бизнес-правила в `inputs/domain/**`.
- **20-architecture-plan** — на основе требований и домена готовит архитектурный план, наполняет `inputs/design/**` (и при необходимости `docs/architecture/**`).
- **20-batches-plan** — планирует работу батчами, ведёт `inputs/batches/pipeline.json` и описательные YAML-файлы батчей.
- **30-bitrix-module-create** — создаёт минимальный каркас модуля Bitrix D7 в `local/modules/<module_id>/`, если проект стартует с нуля.
- **30-code-build** — реализует выбранные батчи: меняет код в `local/**`, запускает проверки и пишет логи в `results/batches/**` и `results/checks/**`.
- **40-tools-init** — настраивает конфиги статических проверок (PHPStan, Deptrac, PHPUnit/CS Fixer) под выбранный модуль в `local/modules/<module_id>/`, чтобы их можно было запускать без ручных правок.
- **40-tests-run** — запускает PHPUnit, PHPStan, PHP-CS-Fixer (dry-run) и другие анализаторы, сохраняя отчёты в `results/checks/**`.
- **40-code-review** — проводит код-ревью изменений в `local/**`, оформляет замечания в `results/reviews/**`.
- **50-docs-update** — синхронизирует реализацию с документацией, обновляет `docs/**`, ADR, cookbook и сопутствующие материалы.
- **60-release-build** — собирает релизные артефакты по диапазону коммитов в `packages/releases/<start>-<end>/` и добавляет README.
- **60-bitrix-update-build** — формирует пакет обновления Bitrix-модуля на основе релиза в `packages/updates/<version>/` (структура `install/`, `updater.php`, README).

## Рабочий процесс

1. **С чего начать?** Сформулируйте идею/задачу и положите черновик в `inputs/` (хоть текстовым файлом). Если непонятно, что делать — спросите у **00-help-assistant**.
2. **Оформить требования.** Запустите **10-requirements-create** — он превратит черновики в структурированные требования в `inputs/requirements/**`.
3. **Понять предметную область.** Запустите **10-domain-describe**, чтобы агент выписал глоссарий, сущности и бизнес-правила в `inputs/domain/**`. Это нужно, чтобы дальше все говорили на одном языке.
4. **Продумать архитектуру.** Передайте собранные требования и домен агенту **20-architecture-plan** — он набросает архитектурный план в `inputs/design/**` (и при необходимости добавит `docs/architecture/**`).
5. **Разбить работу на шаги.** Запустите **20-batches-plan** — агент составит список батчей в `inputs/batches/**` и подскажет DoD для каждого.
6. **Нужен каркас модуля?** Если кода ещё нет, вызовите **30-bitrix-module-create**, он создаст минимальную структуру модуля в `local/modules/<module_id>/`.
7. **Подготовить инструменты.** Если в `composer.json`, `phpstan.neon`, `deptrac.yaml` видны плейсхолдеры (`vendor/module`, `__MODULE_ID__`), запустите **40-tools-init** — он подставит реальный `module_id`/namespace и подготовит конфиги статических проверок.
8. **Реализация.** Запускайте **30-code-build**: берёт первый pending-батч, правит `local/**`, запускает проверки и записывает отчёт в `results/**`.
9. **Проверки по требованию.** **40-tests-run** гоняет PHPUnit/PHPStan/PHP-CS-Fixer/Deptrac и складывает логи в `results/checks/**`.
10. **Ревью.** **40-code-review** делает код-ревью и сохраняет замечания в `results/reviews/**`.
11. **Документация.** **50-docs-update** синхронизирует сделанное с `docs/**`, чтобы знания не терялись.
12. **Поставка.** **60-release-build** собирает релиз в `packages/releases/<range>/`, а **60-bitrix-update-build** — пакет обновления в `packages/updates/<version>/` (с правильным `updater.php` и структурой `install/`).

## Быстрый старт под свой проект

1. Склонируйте репозиторий и выполните `composer install`.
2. Определите `module_id` и namespace модуля (например, `my.vendor` и `My\\Vendor\\`).
3. Запустите агента **40-tools-init**: он подставит `module_id`/namespace в `composer.json`, `phpstan.neon`, `deptrac.yaml` и создаст/обновит вспомогательные конфиги.
4. При необходимости создайте каркас модуля через **30-bitrix-module-create** в `local/modules/<module_id>/`.
5. Заполните черновые требования в `inputs/requirements/` и прогоните цепочку агентов 10→20 для планирования.
6. Запускайте **30-code-build** для реализации батчей, **40-tests-run** для проверок и следуйте остальным шагам рабочего процесса.

### Как понять, что конфиги не инициализированы
- В `composer.json` стоит `vendor/module` или namespace `Vendor\\Module\\`.
- В `phpstan.neon`/`deptrac.yaml` встречается `__MODULE_ID__`.
В этом случае сначала запустите **40-tools-init**.

## Структура репозитория

| Каталог | Назначение |
| --- | --- |
| `.github/` | CI/automation, инструкции агентов и конфиги GitHub. |
| `rules/` | Обязательные правила разработки под Bitrix (`rules/README.md`). |
| `docs/` | Архитектура, ADR, гайды (`docs/README.md`, `docs/guides/*`). |
| `examples/` | Демоматериалы, снапшоты, ссылки, вложения (`examples/README.md`). |
| `inputs/` | Требования, домен, дизайн, батчи, промпты (`inputs/README.md`). |
| `local/` | Исходники Bitrix-модуля (`local/modules/<module_id>/`). |
| `packages/` | Релизы и обновления (`packages/README.md`, `packages/releases/README.md`). |
| `phpstan/` | Bootstrap и заглушки для статического анализа Bitrix-кода. |
| `results/` | Отчёты проверок и батчей (`results/README.md`). |
| `vendor/` | Зависимости Composer (phpunit, phpstan, php-cs-fixer и т.д.). |

### Где читать подробнее
- `docs/README.md` — список постоянных документов и гайдов.
- `docs/guides/copilot-bitrix-overview.md` — обзор workspace и цепочки агентов.
- `inputs/README.md` — как устроены требования/домен/батчи.
- `examples/README.md` — правила и структура примеров/снапшотов.
- `packages/README.md` и `packages/releases/README.md` — как складывать релизы/обновления.
- `results/README.md` — куда сохраняются логи проверок и отчёты батчей.

Дополнительно в корне лежат конфигурации (`phpstan.neon`, `deptrac.yaml`, `.php-cs-fixer.dist.php`) и Composer-манифесты, которыми пользуется исполнительский контур (`30-code-build`, `40-tests-run` и другие агенты) при выполнении проверок.

## Полезные команды

```bash
composer install
vendor/bin/phpunit --log-junit results/checks/phpunit-junit.xml
vendor/bin/phpstan analyse -c phpstan.neon
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff
```

Команды выше запускаются из корня репозитория. Результаты статических анализаторов и тестов можно складывать в `results/checks/` для последующего анализа агентами `20-batches-plan`, `30-code-build`, `40-tests-run` и `50-docs-update`.
