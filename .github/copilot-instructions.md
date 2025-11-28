# Copilot: короткая инструкция для ИИ

Этот репозиторий использует набор специализированных агентов с именами вида `10-*`, `20-*`, `30-*`, `40-*`, `50-*`, `60-*`; подробное описание ролей и цепочки 10→60 см. в разделе «Роли агентов» корневого `README.md`.

## Контекст
- Проект: модуль Bitrix D7 в `local/modules/<module_id>` (PHP 8.1+). Подставьте фактический идентификатор модуля вашего проекта.
- Источник требований: каталог `inputs/` (используйте все файлы, включая `inputs/README.md`).
- Дополнительные правила и процессы: `README.md` (корень) и `inputs/README.md`.
- Архитектурные решения и принципы (seek-пагинация, per-storage EAV, AEAD) описываются в `docs/README.md` и будут выноситься в другие файлы по мере появления.
- **Коммуникация**: все ответы и отчёты пиши на русском языке, включая handoff'ы и комментарии к изменениям.

## Основные требования к коду
- Hexagonal архитектура: домен не обращается к Bitrix ORM, всё через порты и адаптеры.
- Код пишем только под `/local/**`, без правок в `/bitrix/*`.
- PHP-файлы начинаются с `declare(strict_types=1);`, соблюдаем PSR-12.
- SQL миграции и DDL — идемпотентные, соответствуют схеме v2.1.
- Локализации — через `local/modules/<module_id>/lang/ru/**`, никаких строк в коде.

## Рабочие каталоги агентов
- Обязательный вход: всё содержимое `inputs/` (включая `inputs/README.md`).
- Для планирования используйте `inputs/batches/` (`pipeline.json`, `{id}.yaml`, вспомогательные заметки).
- Для артефактов и логов используйте `results/`: `checks/` для отчётов проверок и `batches/` для журналов выполнения.
- Скрипты автоматизации по желанию складывайте в `bin/` (до возврата старых инструментов достаточно описать шаги в README).
- Если нужны дополнительные данные, создавайте соседние каталоги прямо в корне и сразу документируйте их назначения в `README.md`.

- PHPUnit: `vendor/bin/phpunit --log-junit results/checks/phpunit-junit.xml`.
- PHPStan: `vendor/bin/phpstan analyse -c phpstan.neon --error-format=json > results/checks/phpstan-report.json`.
- PHP-CS-Fixer: `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff > results/checks/phpcs-report.txt`.
- PHPCompatibility (PHPCS): `vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.1-8.4 --extensions=php local/modules/<module_id> > results/checks/phpcompatibility-report.txt` — добавьте шаг в CI, чтобы каждый PR проверял совместимость с поддерживаемыми версиями PHP.
- Deptrac и прочие проверки подключайте по необходимости.

## Роли агентов
- **20-batches-plan** (`.github/agents/20-batches-plan.agent.md`): работает с `inputs/**`, ведёт `inputs/batches/pipeline.json`, описывает батчи в `inputs/batches/{id}.yaml` и фиксирует DoD. Перед планированием проходит чеклист: редакция проекта и PHP-версия, наличие `composer`, наличие корневых конфигов (`phpunit`, `phpstan.neon`, `.php-cs-fixer.dist.php`), наличие `local/phpunit/bootstrap.php`, текущее состояние `/local/modules`, миграций и стандартов. Пробелы описываются отдельными батчами или рисками.
- **30-code-build** (`.github/agents/30-code-build.agent.md`): берёт первый pending-батч, вносит изменения в `local/**`, запускает проверки, обновляет отчёты и статусы. Дополнительно контролирует права (`CMain::GetGroupRight()`), безопасность форм (`bitrix_sessid_post`, `check_bitrix_sessid`, `B_PROLOG_INCLUDED`), локализацию (все строки в `local/modules/<module_id>/lang/ru/...`), кэширование (`StartResultCache`, теговый кэш), логирование, утверждённые UI-стандарты (например, BEM с `--`, иконки `sf-icon`), правила для HL-блоков (префиксы `SF`/`sf_`, `UF__`).
- **50-docs-update** (`.github/agents/50-docs-update.agent.md`): читает `results/batches/*.md`, требования и код, обновляет `docs/**` (ADR, спецификации, cookbook, OPS). Не придумывает данные из головы: если информации не хватает, создаёт список вопросов или предлагает агентам групп `10-*`/`20-*` новый батч.
- **60-release-build** (`.github/agents/60-release-build.agent.md`): по запросу собирает релизный пакет, проверяет коммиты, формирует каталог `packages/releases/<start>-<end>` с изменёнными файлами и README, может подсказать последние коммиты, если пользователь их не помнит.
- **60-bitrix-update-build** (`.github/agents/60-bitrix-update-build.agent.md`): принимает готовый релиз, просит новую версию модуля и собирает пакет обновления в `packages/updates/<version>/` (файлы модуля без префикса `local`, `updater.php`, README).
- **30-bitrix-module-create** (`.github/agents/30-bitrix-module-create.agent.md`): запрашивает код/название модуля, создаёт минимальную структуру в `local/modules/<module_id>/` (install/index.php, version.php, include.php, lang, README, .gitkeep).

## Быстрые ссылки
- Архитектура и процессы: `docs/README.md` (расширяйте по мере появления отдельных документов и фиксируйте ссылки здесь же).
- Ошибки, DSL и cookbook: пока не заведены — создайте файлы в `docs/` и обновите README, как только появится контент.
- Примеры данных: `examples/snapshots/*.jsonl`, крупные проекты в `examples/links/`, вложения — `examples/files/`.

Документируйте новые папки и процессы прямо в корневом `README.md` (при необходимости добавляйте отдельные файлы в `inputs/`). Так любой агент поймёт технологию за пару минут.
