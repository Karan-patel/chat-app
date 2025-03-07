# Chat Application

This is a lightweight, RESTful chat application built with PHP and the Slim framework. It allows users to create groups,
join them, send messages, and list messages or groups, leveraging a SQLite database for persistence. 
The project showcases modern PHP practices, design patterns, and optimizations for scalability and maintainability.

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
    - *Single Responsibility Principle (SRP)*: Each class has a single purpose (e.g., `GroupController` for group
      operations).
    - *Open/Closed Principle (OCP)*: The app supports extensions without altering existing code.
    - *Interface Segregation Principle (ISP)*: Dependencies are kept minimal and specific.
    - *Dependency Inversion Principle (DIP)*: High-level modules rely on abstractions via DI.

- **Dependency Injection (DI)**:
    - Implemented using **PHP-DI** to manage dependencies, ensuring loose coupling and easier testing.

- **Factory Pattern**:
    - Used in **AppFactory** to centralize app creation and configuration.

- **Middleware Pattern**:
    - Employed via **UserMiddleware** for authentication, promoting separation of concerns.

- **Configuration Management**:
    - Settings are externalized in **.env** and **config.php**, following the **12 Factor App** methodology.

- **Error Handling**:
    - Custom exceptions (e.g., `BadRequestException`, `NotFoundException`) and **Monolog** for **centralized logging.**

- **Autoloading and PSR Standards**:
    - **PSR-4 Autoloading** via Composer reduces manual includes.
    - **PSR-12 Coding Standards** ensure consistent code.

- **Separation of Concerns (SoC)**:
    - Distinct layers for routing, business logic, data access, and middleware enhance maintainability.

- **Testability**:
    - Supported by **PHPUnit** with comprehensive tests to verify functionality and prevent regressions.

### ⚠️ Error Types

The application categorizes and handles errors effectively:

- **Client Errors (4xx)**:
    - *400 Bad Request*: Invalid input (e.g., missing `X-Username`).
    - *403 Forbidden*: Unauthorized actions (e.g., messaging without group membership).
    - *404 Not Found*: Missing resources (e.g., invalid group ID).

- **Server Errors (5xx)**:
    - *500 Internal Server Error*: Database failures or unexpected issues, logged for resolution.

- **Custom Exceptions**:
    - `BadRequestException`, `NotFoundException`, `ForbiddenException`, `DatabaseException` for precise error handling.

## 🧐📖 Familiarization

To get started:

- **Prerequisites**: PHP 8.x, Composer, sqlite(& .db file), Docker (optional).
- **Setup**: Clone the repo, run `composer install`, ensure `schema.sql` is present.
- **db**: `db/chat.db` file (db file as per path in `.env` config **must** be present)
- **Key Files**: Review `public/index.php`, `src/AppFactory.php`, and `tests/`.
- **Run Locally**: Use `php -S localhost:8000 -t public/` to start the server.
- **Run Phar**: Use `php -S localhost:8000 chat-app.phar` after creating `chat-app.phar`.

Familiarity with REST APIs, PHP namespaces, and DI will help navigate the codebase.

#### Database Schema and Operations

Create db as per file configured in `.env`.

```sql
sqlite3 db/chat.db #path as per configuration
```

The database schema is defined in `schema.sql`, read during initialization, ensuring maintainability. The schema
includes:

- `users`: Stores user IDs and usernames with a unique constraint.
- `groups`: Stores group details, including the creator, with a foreign key to `users`.
- `group_members`: Tracks membership with a composite primary key for efficiency, ensuring no duplicate memberships.
- `messages`: Stores messages with timestamps, using SQLite’s `CURRENT_TIMESTAMP` for automatic setting, and foreign
  keys to `groups` and `users`.

The `Database` class uses PDO with prepared statements to prevent SQL injection, ensuring security. Queries are
optimized for efficiency, such as joining tables in `getMessagesByGroup` to fetch sender usernames in one call, avoiding
N+1 query problems.

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


#### 📦 Using PHAR File

The PHP equivalent of a JAR file is a PHAR file, created using PHP’s `Phar` class. The `build-phar.php` script
builds `chat-app.phar`, including:

- `app.php`, `src/`, `vendor/`, `.env` and `schema.sql`.
- Excludes `tests/` and `db/chat.db`, as the latter must be separate for write operations.

To build phar:

```bash
php build-phar.php
```

It will create 'chat-app.phar', once created you can run application with below command.

```bash
php -S localhost:8000 chat-app.phar
```

### 💻 Running in local

If you want to run directly in local, run via below command.

```bash
php -S localhost:8000 -t public #<path to 'public' dir where index file located>
```

### 🐳 Running in container (Docker)

1. **Build the Docker image:**

```bash
docker build -t chat-app:latest .
```

2. **Run docker container**

```sh
  docker run -d -p 8000:8000 -v "$(pwd):/var/www" --name chat-app-container chat-app #For Linux / macOS (Bash, Zsh, etc.)
  docker run -d -p 8000:8000 -v "${PWD}:/var/www" --name chat-app-container chat-app #Windows (PowerShell)
  docker run -d -p 8000:8000 -v "%cd%:/var/www" --name chat-app-container chat-app   #For Windows CMD
```

3. **Push to Docker Hub (replace your-dockerhub-username with your username):**

```bash
docker tag chat-app:latest your-dockerhub-username/chat-app:latest #tag
docker push your-dockerhub-username/chat-app:latest                #push
```

## ⚡ Important Before Deployment

Before deploying, **ensure that the database file configured in `.env` exists** and has the required access **permissions**. Without it, the application might not function correctly! 🚨

👉 If the database is not created yet, follow the instructions above to set it up.


## ✅ Verify Deployment

After deploying the application, ensure it is running correctly by accessing:

🔗 **http://localhost:8080**

If successful, the response should be:

```json
{
  "message": "Hello from bunq!"
}
```

## 🐳 Containerization
This application is fully containerized using Docker, making it platform-independent and capable of running anywhere—Windows, Linux, macOS, or any cloud provider—without modification.

## ☸️ Scalability with Kubernetes

Since the app is `containerized`, it can be seamlessly scaled using Kubernetes (K8s), a powerful orchestration platform. By adding a k8s.yaml configuration file with appropriate container, service, and pod details, you can:

- **Scale Horizontally**: Spin up multiple pods to handle increased traffic.
- **Ensure High Availability**: Distribute instances across nodes for fault tolerance.
- **Automate Management**: Leverage K8s features like auto-scaling, self-healing, and load balancing.

## 🔧 Troubleshooting

- **PHP not found: Ensure PHP 8.2+ is installed and in your PATH.**

- **Composer errors: Verify internet connection and run composer update.**

- **Docker build fails: Check Dockerfile syntax and ensure Docker is running.**

- **Kubernetes issues: Confirm cluster access and correct image name in k8s.yaml.**

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
