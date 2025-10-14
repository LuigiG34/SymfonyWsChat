# SymfonyWsChat

A Symfony project demonstrating a real-time chat over WebSockets (via Mercure) with a simple UI.

---

## 1) Requirements
1. Docker
2. Docker Compose
3. (Windows) WSL2

---

## 2) Install

```
git clone https://github.com/LuigiG34/SymfonyWsChat
cd SymfonyWsChat

docker compose up -d --build

docker compose exec php composer install

docker compose exec php php bin/console doctrine:database:create

docker compose exec php php bin/console make:migration

docker compose exec php php bin/console doctrine:migrations:migrate
```

---

## 3) Run project

1. Open 1 normal and 1 private tab and repeat the next steps
2. Go to `http://localhost:8080/` 
3. Register a user (and a second user in private tab)
4. Login with that same user
5. Search for the other user
6. Start sending messages