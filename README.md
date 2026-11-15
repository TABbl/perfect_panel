# perfect_panel
Задание 1
```sql
WITH candidate_users AS (
    SELECT id, first_name, last_name
    FROM users
    WHERE birthday <= CURDATE() - INTERVAL 7 YEAR
      AND birthday >  CURDATE() - INTERVAL 18 YEAR
),
eligible_users AS (
    SELECT ub.user_id
    FROM user_books ub
    JOIN books b ON b.id = ub.book_id
    WHERE ub.user_id IN (SELECT id FROM candidate_users)
    GROUP BY ub.user_id
    HAVING COUNT(*) = 2
       AND COUNT(DISTINCT b.author) = 1
       AND MAX(DATEDIFF(COALESCE(ub.return_date, CURDATE()), ub.get_date)) <= 14
)
SELECT
    CONCAT_WS('; ',
        cu.id,
        CONCAT(cu.first_name, ' ', cu.last_name),
        MAX(b.author),
        GROUP_CONCAT(b.name ORDER BY ub.get_date SEPARATOR ', ')
    ) AS result
FROM eligible_users eu
JOIN candidate_users cu ON cu.id = eu.user_id
JOIN user_books ub ON ub.user_id = eu.user_id
JOIN books b ON b.id = ub.book_id
GROUP BY cu.id, cu.first_name, cu.last_name;
```

Пояснение - сначала отсекаем всех неподходящих юзеров и только потом делаем выборку.


Задание 2
Перейти в ./task-2 и выполнить docker compose up -d --build. Запустятся контейнеры и автоматически выполнится команда
для обращения в coinpaprika и заполнения бд.  В .env лежит в API_AUTH_TOKEN токен для запросов. Для оптимизации 
используется кеш (Redis). При недоступном Redis идет фолбек на бд. Так как в курсах важна точно, использовал bcmath (
возможно намудрил с проверкой точности чисел, так как раньше работал только с копейками). Предложения: 1) реализовать rate
limit; 2) уточнить в бизнес логике, что делать если курс валюты не обновлялся n минут?


Получить все курсы - curl -i -G "http://localhost:80/api/v1"   -H "Authorization: Bearer Xk7pQ2mZ9vLr4Bw_T1nH8yJc-R5sD3fG6aE0hN9iU7oP2qW-K4Xk7pQ2mZ9vLr4Bw_T1nH8yJc-R5sD3fG6aE0hN9iU7oP2qW-K4tV8bC1xM5zY6j_

Обменять 1000 USD в BTC - curl -i -X POST "http://localhost/api/v1?method=convert&currency_from=USD&currency_to=BTC&value=1000" \
-H "Authorization: Bearer Xk7pQ2mZ9vLr4Bw_T1nH8yJc-R5sD3fG6aE0hN9iU7oP2qW-K4tV8bC1xM5zY6j_"

UPD: Дополнительно потратил полтора часа чтобы привести код в более структурированный вид (хоть и заложенные мной 9 часов вышли, но я не мог оставить его в таком виде)
