# SIMAI Copilot: короткая инструкция для ИИ

## Контекст
- Проект: модуль Bitrix D7 `local/modules/{vendor}.{module}` (PHP 8.1+).
- Источник требований: `copilot/inputs/requirements.md` (единственный обязательный файл внутри `copilot/`).
- Все процессы описаны в `copilot/README.md`.
- Архитектура, ADR и cookbook — внутри `docs/`.
- **Коммуникация**: все ответы и отчёты пиши на русском языке.

## Основные требования к коду
- Hexagonal-подход: домен не обращается к Bitrix ORM напрямую.
- Код размещаем только под `/local/**`.
- `declare(strict_types=1);` во всех PHP-файлах, PSR-12.
- SQL миграции идемпотентные.
- Локализация только через `lang/ru/**`.

## Как пользоваться `copilot/`
- Минимум: `inputs/requirements.md`, `config/*`, README.
- При старте планирования создавайте `copilot/batches/` и `copilot/results/` по README.
- Если нужна автоматизация, верните `copilot/bin/` из истории и обновите README.

## Проверки качества
- PHPUnit: `vendor/bin/phpunit --configuration copilot/config/phpunit.xml --log-junit copilot/results/checks/phpunit-junit.xml`.
- PHPStan: `vendor/bin/phpstan analyse -c copilot/config/phpstan.neon --error-format=json > copilot/results/checks/phpstan-report.json`.
- PHP-CS-Fixer: `vendor/bin/php-cs-fixer fix --config=copilot/config/rules.php-cs-fixer.php --dry-run --diff > copilot/results/checks/phpcs-report.txt`.
- Deptrac: `vendor/bin/deptrac analyse --no-interaction > copilot/results/checks/deptrac-report.txt`.

## Роли агентов
- **Planner** (`.github/agents/planner.agent.md`): планирует батчи, проверяет наличие конфигов и описывает DoD.
- **Builder** (`.github/agents/builder.agent.md`): реализует батчи, запускает проверки, следит за правами, безопасностью, локализацией и кэшем.
- **Documentation** (`.github/agents/documentation.agent.md`): обновляет `docs/**` по итогам батчей, фиксирует вопросы.

## Быстрые ссылки
- Архитектура: `docs/ARCHITECTURE.md`, `docs/spec/*`, `docs/adr/`.
- Cookbook: `docs/COOKBOOK/**/*.md`.
- Примеры данных: `example/*.jsonl`.

Всегда сначала обновляйте `copilot/README.md`, а уже потом добавляйте новые папки или процессы.
