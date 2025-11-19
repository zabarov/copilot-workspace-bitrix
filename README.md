# SIMAI Copilot Template

Этот репозиторий-шаблон содержит минимальный набор файлов для разработки Bitrix D7 модулей по процессу SIMAI + Copilot. Он включает:

- базовую структуру `local/modules/{vendor}.{module}` без реализации;
- документацию и ADR-шаблоны в `docs/`;
- настроенный Copilot-.workspace в `copilot/` с конфигами PHPUnit/PHPStan/PHP-CS-Fixer;
- инструкции для агентов в `.github/agents/` и общие правила в `.github/copilot-instructions.md`.

## Как пользоваться

1. Сделайте новый репозиторий из этого шаблона.
2. Обновите `composer.json`, `docs/` и `copilot/inputs/requirements.md` под свой проект.
3. Запускайте планирование через Copilot Planner, реализуйте батчи через Builder и фиксируйте документацию через Documentation Agent.
4. Храните код только в `local/modules/{vendor}.{module}` и соблюдайте правила из `docs/`.
