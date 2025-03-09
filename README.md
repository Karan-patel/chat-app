# Chat Application

This is a lightweight, RESTful chat application built with PHP and the Slim framework. It allows users to create groups, join them, send messages, and list messages or groups, leveraging a SQLite database for persistence. The project showcases modern PHP practices, design patterns, and optimizations for scalability and maintainability.

## 📂 Directory Structure

🗂️**chat-app**/  
├── 📁**config**/  
│ └── config.php # Configuration loading from .env  
├── 📁**logs**/  
│ └── app.log # Log file (generated)  
├── 📁**public**/  
│ └── index.php # Application entry point  
├── 📁**src**/  
│ ├── AppFactory.php # Factory for Slim app creation  
│ ├── Database.php # SQLite database handling  
│ ├── Exceptions.php # Custom exception classes  
│ ├── GroupController.php # Group-related endpoints  
│ ├── MessageController.php # Message-related endpoints  
│ ├── Routes.php # Route definitions  
│ └── UserMiddleware.php # Middleware for user authentication  
├── 📁**tests**/  
│ ├── AllTests.php   #Unit test for most of scenario with mock  
│ ├── EndToEndTest.php # End-to-end tests with in-memory DB operation  
│ ├── FullAppEndToEndTest.php # Original end-to-end tests even loading entire application from scratch  
│ ├── GroupControllerTest.php # Unit test for GroupController  
│ └── UserMiddlewareTest.php  # Unit test for UserMiddleware  
├── .env # Environment variables (not committed)    
├── composer.json # Composer dependencies and autoloading    
├── Dockerfile # Docker configuration for deployment    
├── phpunit.xml # Testcase config file    
└── schema.sql # SQLite schema for tables  

## 🛠️⚡ Technology and Framework

- **PHP**: Version 8.x for modern features like typed properties and attributes.
- **Slim Framework**: A micro-framework (v4.x) for routing and middleware, chosen for its lightweight nature and
  flexibility.
- **SQLite**: A serverless database for simplicity and portability.
- **PHP-DI**: Dependency injection container for managing dependencies.
- **Monolog**: Logging library for error tracking.
- **Dotenv**: Loads environment variables from `.env` for configuration.
- **PHPUnit**: Testing framework for unit and end-to-end tests.

### 🎨✨ Design Patterns and Optimizations

- **SOLID Principles**:
  - *Single Responsibility*: Classes like `GroupController` handle one task.
  - *Open/Closed*: Extensible without modifying core code.
  - *Interface Segregation*: Minimal, specific dependencies.
  - *Dependency Inversion*: Uses DI for loose coupling.

- **Dependency Injection**: Via **PHP-DI** for testability and flexibility.
- **Factory Pattern**: `AppFactory` centralizes app setup.
- **Middleware**: `UserMiddleware` for authentication.
- **12 Factor App**: Config in `.env` and `config.php`.
- **Error Handling**: Custom exceptions and Monolog logging.
- **PSR Standards**: PSR-4 autoloading, PSR-12 coding style.
- **Separation of Concerns**: Clear layers for routing, logic, and data.
- **Testability**: Comprehensive PHPUnit tests.

### ⚠️ Error Types

- **Client Errors (4xx)**:
  - *400 Bad Request*: Invalid input (e.g., missing `X-Username`).
  - *403 Forbidden*: Unauthorized actions (e.g., non-member messaging).
  - *404 Not Found*: Missing resources (e.g., invalid group ID).
- **Server Errors (5xx)**:
  - *500 Internal Server Error*: Logged database or runtime failures.
- **Custom Exceptions**: `BadRequestException`, `NotFoundException`, etc.

## 🧐📖 Familiarization

To get started:

### Prerequisites
- PHP 8.x
- Composer
- SQLite (bundled with PHP)
- Docker (optional)

Familiarity with REST APIs, PHP namespaces, and DI will help navigate the codebase.

### Setup
1. Clone the repo:
```bash
git clone <repo-url> chat-app
cd chat-app
```

2. Install dependencies:
```bash
composer install
```
3. Configure `.env` (copy `.env`.example if provided):
```bash
composer DB_PATH=db/chat.db #path relative to root dir (i.e chat-app)
```
**Note: `DB_PATH` is relative to the project root (`chat-app/`), e.g., `db/chat.db` resolves to `chat-app/db/chat.db`.**
4. Initialize the database:

```bash
sqlite3 db/chat.db
```

#### Database Schema


Defined in `schema.sql`.

- `users`: `(id, username UNIQUE)` Stores user IDs and usernames with a unique constraint.
- `groups`: `(id, name, created_by)` Stores group details, including the creator, with a foreign key to `users`.
- `group_members`: `(group_id, user_id)` Tracks membership with a composite primary key for efficiency, ensuring no duplicate memberships.
- `messages`: `(id, group_id, user_id, message, timestamp)` Stores messages with timestamps, using SQLite’s `CURRENT_TIMESTAMP` for automatic setting, and foreign
  keys to `groups` and `users`.

The `Database` class uses PDO with prepared statements to prevent SQL injection, ensuring security. Queries are
optimized for efficiency, such as joining tables in `getMessagesByGroup` to fetch sender usernames in one call, avoiding
**N+1** query problems.

## 🌍 Endpoints

**All endpoints require the `X-Username` header for user identification.**

|  Method  | Endpoint                | Description         | Example Request                                                                                                                             |
|:--------:|:------------------------|---------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| **GET**  | `/groups`               | List all groups     | `curl -H "X-Username: alice" http://localhost:8000/groups`                                                                                  |
| **POST** | `/groups`               | Create a group      | `curl -X POST -H "X-Username: alice" -H "Content-Type: application/json" -d '{"name":"General"}' http://localhost:8000/groups`              |
| **POST** | `/groups/{id}/join`     | Join a group        | `curl -X POST -H "X-Username: bob" http://localhost:8000/groups/1/join`                                                                     |
| **POST** | `/groups/{id}/messages` | Send a message      | `curl -X POST -H "X-Username: alice" -H "Content-Type: application/json" -d '{"message":"Hi all"}' http://localhost:8000/groups/1/messages` |
| **GET**  | `/groups/{id}/messages` | List group messages | `curl -H "X-Username: alice" http://localhost:8000/groups/1/messages`                                                                       |

The `UserMiddleWare` extracts the `X-Username` header, creates the user if not exists, and attaches the user ID to the
request, following the Chain of Responsibility pattern for middleware processing.

## 🚀🌍 Deployment


#### 📦 Using PHAR 

The PHP equivalent of a JAR file is a PHAR file, created using PHP’s `Phar` class. The `build-phar.php` script
builds `chat-app.phar`, including:

- `config/`, `public/`, `src/`, `vendor/`, `.env` and `schema.sql`.
- Excludes `tests/` and no relevant files, as the latter must be separate for write operations.


Build a phar for a portable app:

```bash
php build-phar.php
```

Run (requires public/index.php as stub entry):

```bash
php chat-app.phar
```
**Note: SQLite DB (`db/chat.db`) must be external, relative to the PHAR’s execution directory.**


### 💻 Running Locally

If you want to run directly in local, run via below command.

```bash
php -S localhost:8000 -t public
```

### 🐳 Running in Docker

1. **Build the Docker image:**

```bash
docker build -t chat-app:latest .
```

2. **Run docker container**

```sh
docker run -d -p 8000:8000 -v "$(pwd):/var/www" chat-app:latest  # Linux/macOS
docker run -d -p 8000:8000 -v "%cd%:/var/www" chat-app:latest    # Windows CMD
```

3. **Push to Docker Hub (replace your-dockerhub-username with your username):**

```bash
docker tag chat-app:latest your-dockerhub-username/chat-app:latest #tag
docker push your-dockerhub-username/chat-app:latest                #push
```

## ⚡ Important Before Deployment

🚨**Ensure that the database file configured in `.env` exists** and has the required access **permissions**. 
Without it, the application might not function correctly! 

👉 If the database is not created yet, follow the instructions above to set it up.


## ✅ Verify Deployment

Test the root endpoint:

🔗 **http://localhost:8080**

or

```bash
curl http://localhost:8000
```

If successful, the response should be:

```json
{
  "message": "Hello from bunq!"
}
```

## 🧪 Running Tests

```bash 
vendor/bin/phpunit
```

## 🐳 Containerization
This application is fully containerized using Docker, making it platform-independent and capable of running anywhere—Windows, Linux, macOS, or any cloud provider—without modification.

## ☸️ Scalability with Kubernetes

Since the app is `containerized`, it can be seamlessly scaled using Kubernetes (K8s), a powerful orchestration platform. By adding a k8s.yaml configuration file with appropriate container, service, and pod details, you can:

- **Scale Horizontally**: Spin up multiple pods to handle increased traffic.
- **Ensure High Availability**: Distribute instances across nodes for fault tolerance.
- **Automate Management**: Leverage K8s features like auto-scaling, self-healing, and load balancing.

e.g.

Add a k8s.yaml for Kubernetes deployment:

```yaml 
apiVersion: apps/v1
kind: Deployment
metadata:
  name: chat-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: chat-app
  template:
    metadata:
      labels:
        app: chat-app
    spec:
      containers:
      - name: chat-app
        image: yourusername/chat-app:latest
        ports:
        - containerPort: 8000
```

Apply with ```kubectl apply -f k8s.yaml```.

## 🔧 Troubleshooting

- **PHP not found: Install PHP 8.x and add to PATH.**

- **Composer errors: Verify internet connection and run composer update.**

- **Docker port mismatch: Use 8000, not 8080.**

- **PHAR fails: Ensure public/index.php is in the PHAR stub.**

- **Database errors: Verify chat.db exists and is writable:**

 ```bash
  chmod 666 chat.db #<your db path e.g. db.chat.db>
 ```

## 🛠 License & Freedom to Build!

This project is open-source because **great ideas should be shared**! 🚀

Feel free to **fork it, tweak it, break it, improve it**, and send a PR if you make it better!  
Just be awesome and give credit where it's due.

## 🚀 The Journey Begins!
This is my **first-ever** PHP application—just the start of something awesome! 🌟
Contributions, feedback, or just a virtual high-five? All are welcome! 🤝

Let's build, break, and innovate—together! 💡🔥

Let's connect! 🤝 Find me on [LinkedIn](https://www.linkedin.com/in/karanptel/) and let's build something **epic!** 💡🔥  
