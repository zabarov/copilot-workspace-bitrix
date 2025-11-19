# Copilot Workspace Template

Минимальный набор файлов внутри `copilot/`:

| Что оставляем | Где лежит | Комментарий |
| --- | --- | --- |
| ТЗ/контекст | `copilot/inputs/requirements.md` | Отсюда Planner начинает работу. |
| Конфиги проверок | `copilot/config/*.php|*.neon` | Используются Builder’ом и CI. |
| README | `copilot/README.md` | Правила расширения каталога. |

Остальные папки (`batches/`, `results/`, `bin/`) создавайте по мере необходимости, следуя инструкциям ниже.

## 1. Планирование батчей
1. Создайте `copilot/batches/pipeline.json` со списком задач (id/title/description/kind/depends_on/allow_retries/status).
2. Для сложных задач добавьте `copilot/batches/{id}.yaml` с `objective/inputs/deliverables/checks/notes`.
3. Planner отвечает за актуальность `pipeline.json`, Builder — за статусы.

### Мини-чеклист Planner’а
- Редакция Bitrix, версия PHP, composer.
- Наличие конфигов `copilot/config/phpunit.xml`, `copilot/config/phpstan.neon`, `copilot/config/rules.php-cs-fixer.php`, `local/phpunit/bootstrap.php`.
- Текущее состояние `/local/modules`, миграций, SIMAI SF4 UI.
- Требования к данным, правам, локализации, логированию.

## 2. Результаты и отчёты
- Создайте `copilot/results/` с подпапками `checks/`, `batches/`, `state/`, `snapshot/`.
- Используйте Markdown-шаблон для отчётов (`статус`, `что делали`, `изменённые файлы`, `проверки`).

### Качество для Builder’а
- Запуск PHPUnit, PHPStan, PHP-CS-Fixer, Deptrac.
- Контроль прав, sessid, `B_PROLOG_INCLUDED`, локализация, кэширование, логирование, SIMAI SF4 UI.

## 3. Автоматизация (опционально)
1. Верните `copilot/bin/` и конфиги оркестратора.
2. Настройте `.env.local`, `copilot/results/state/last-run.json`.
3. Запускайте `./copilot/bin/codex-orchestrate --stage=...` при необходимости.

## 4. Документация
- После каждого батча запускайте Documentation Agent, чтобы обновить `docs/`.
- Если раздела нет — создайте новую запись, привяжите к батчу и зафиксируйте вопросы.
