# Chat Application

This is a lightweight, RESTful chat application built with PHP and the Slim framework. <br>
It allows users to **create groups, join them, send messages, and list messages**, leveraging a **SQLite** database for persistence.<br>
The project showcases modern PHP practices, **clean architecture, explicit design decisions**, and optimizations for scalability and maintainability.

---

## Architecture Overview

This project follows **Hexagonal Architecture (Ports & Adapters)**, often referred to as **Clean Architecture**.

The core principle is simple:

> **Business logic is isolated from frameworks, databases, and delivery mechanisms.**

### Architectural Mapping

<div>
<strong>Hexagonal Architecture (Ports & Adapters)</strong>
<table>
<tr>
<td align="center"><strong>HTTP Layer</strong><br/>&#40;Slim Framework&#41;<br/><em>Adapter</em></td>
  </tr>
  <tr>
    <td align="center">↓</td>
  </tr>
  <tr>
    <td align="center"><strong>Application Services</strong><br/><em>Use Cases</em></td>
  </tr>
  <tr>
    <td align="center">↓</td>
  </tr>
  <tr>
    <td align="center"><strong>Domain</strong><br/>(Entities + Ports)</td>
  </tr>
  <tr>
    <td align="center">↑</td>
  </tr>
  <tr>
    <td align="center"><strong>Infrastructure</strong><br/>(SQLite Adapters)</td>
  </tr>
</table>
</div>


### Layer Responsibilities

- **Domain**
  - **Pure entities** and **repository interfaces** (*ports*)
  - Framework and storage-**agnostic**
  - Represents the **core business model**

- **Application Services**
  - Orchestrate use cases
  - Enforce business rules, validation, and authorization
  - Depend only on **domain** ports

- **Infrastructure (Adapters)**
  - SQLite repositories implement domain ports
  - **Replaceable** without modifying business logic

- **HTTP Layer (Adapters)**
  - Slim controllers adapt **HTTP** requests to application use cases
  - Contains **no business** or **authorization** logic

### Architectural Rule

> **All dependencies point inward toward the Domain.**

Frameworks and databases are treated as **details**, not foundations.

---

## Why Hexagonal Architecture

- Keeps business logic **isolated and testable**
- Prevents framework and database **lock-in**
- Makes authorization and validation **explicit**
- Enables **infrastructure changes** without refactoring core logic
- Favors **long-term maintainability** over short-term convenience

---

## Key Design Decisions

### Authorization as a Business Rule
- Groups are public and can be joined by anyone
- **Only group members can read or send messages**
- Authorization is enforced in **Application Services**
- Violations raise semantic exceptions (`ForbiddenException`)

Authorization is treated as **domain logic**, not middleware logic.

---

### Centralized Error Handling
- Application layer throws domain-specific exceptions
- A single global error handler maps exceptions to HTTP responses

| Exception | HTTP Status |
|---------|-------------|
| **ValidationException** | 400 |
| **ForbiddenException**  | 403 |
| **NotFoundException**   | 404 |
| **Unhandled error**     | 500 |

Controllers only handle **successful paths**.

---

### Persistence Strategy
- SQLite: simple, deterministic, file-based
- Database path injected via environment configuration
- Same persistence works locally and in Docker
- Data access abstracted behind repository interfaces

---

### Dependency Inversion
- Application depends on **interfaces**, not implementations
- Infrastructure implements those interfaces
- Storage engine can be replaced without touching business logic

---

## Project Structure

🗂️**src/**  
├── 📁**Domain/**  
│ └── # Entities + repository contracts (**pure**)  
├── 📁**Application/**  
│ └── # Business rules, validation, authorization  
├── 📁**Infrastructure/**  
│ └── # SQLite implementations  
├── 📁**Http/**   
│ └── # Controllers, middleware, response helpers  
├── **composer.json** # Composer dependencies and autoloading    
├── **Dockerfile** # Docker configuration for deployment    
└── **phpunit.xml** # Testcase config file    

Each folder represents a **responsibility**, not a framework artifact.

---

##  Running the Application

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
3. Prepare database directory (make sure to have both **read/write** access):
```bash
mkdir data
```

4. Run migrations:

```bash
sqlite3 data/database.sqlite < migrations/001_initial.sql
```

5. Start the server:

```bash
php -S localhost:8000 -t public
```

🌍**Access**:
```bash
http://localhost:8000
```

**If successful, the response should be:**:
```json
{
  "status": "success",
  "data": {
    "message": "Hello from bunq!"
  }
}
```

### 🐳 Running in Docker
This project uses SQLite.  
To ensure stable behavior across all operating systems (**Windows**, **macOS**, **Linux**), the recommended setup is:

- Keep the application fully inside the Docker image
- Store the SQLite database in a **Docker named volume**
- Expose the API on port **8000**

This follows modern Docker best practices for PHP applications.

### 1️⃣ Build and start containers

```bash
docker compose up --build
```

### 2️⃣ Database & persistence

- SQLite database is stored in **./data/database.sqlite**
- Mounted into the **container** as a **volume**
- Data persists across restarts

### 3️⃣ Run migrations (one-time)
A Docker volume ensures database persistence across restarts and prevents DB initialization failures.
```bash
  docker run --rm ^
  -v "${PWD}\data:/app/data" ^
  -v "${PWD}\migrations:/app/migrations" ^
  php:8.2-cli-alpine ^
  sh -c "apk add --no-cache sqlite && sqlite3 /app/data/database.sqlite < /app/migrations/001_initial.sql"
```

### 4️⃣ Access the app
```bash
http://localhost:8000
```
___

## 🌍 API Usage

**All endpoints require the `X-User-Id` header for user identification.**

|  Method  | Endpoint                | Description         | Example Request                                                                                                                             |
|:--------:|:------------------------|---------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| **POST** | `/groups`               | Create a group      | `curl -X POST -H "X-User-Id: alice" -H "Content-Type: application/json" -d '{"name":"General"}' http://localhost:8000/groups`              |
| **POST** | `/groups/{id}/join`     | Join a group        | `curl -X POST -H "X-User-Id: bob" http://localhost:8000/groups/1/join`                                                                     |
| **POST** | `/groups/{id}/messages` | Send a message      | `curl -X POST -H "X-User-Id: alice" -H "Content-Type: application/json" -d '{"message":"Hi all"}' http://localhost:8000/groups/1/messages` |
| **GET**  | `/groups/{id}/messages` | List group messages | `curl -H "X-User-Id: alice" http://localhost:8000/groups/1/messages`                                                                       |

___

## Testing Strategy

- Core business logic tested at the **Application Service** level
- SQLite repositories tested with **in-memory** databases
- HTTP/framework code intentionally kept **thin** and lightly tested

Focus is on business rules, not framework internals

#### Run tests:

```bash
vendor/bin/phpunit
```
___

## ⚡ Important Before Deployment

🚨**Ensure that the database directory `data` exists** and has the required access **permissions**. 
Without it, the application might not function correctly! 

👉 If the database is not created yet, follow the instructions above to set it up.

#### Verify DB configured and tables created.

```bash
docker run --rm -v "${PWD}\data:/app/data" php:8.2-cli-alpine sh -c "apk add --no-cache sqlite && sqlite3 /app/data/database.sqlite '.tables'"
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
  replicas: 1  # SQLite is file-based → single replica by design
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
          image: your-dockerhub-username/chat-app:latest
          ports:
            - containerPort: 8080
          env:
            - name: SQLITE_DB_PATH
              value: /app/data/database.sqlite
          volumeMounts:
            - name: db
              mountPath: /app/data
      volumes:
        - name: db
          emptyDir: {}   # for demo purposes only

---
apiVersion: v1
kind: Service
metadata:
  name: chat-app
spec:
  selector:
    app: chat-app
  ports:
    - port: 80
      targetPort: 8080
  type: ClusterIP
```

Apply with ```kubectl apply -f k8s.yaml```.

## Docker & Runtime Choices

- **Multi-stage** Docker build
- Runs as a **non-root** user
- SQLite persisted via **mounted volume**

**Same container works locally and in CI**

## 🚀 The Journey Begins!
This is my **first-ever** PHP application—just the start of something awesome! 🌟
Contributions, feedback, or just a virtual high-five? All are welcome! 🤝

Let's build, break, and innovate—together! 💡🔥

Let's connect! 🤝 Find me on [LinkedIn](https://www.linkedin.com/in/karanptel/) and let's build something **epic!** 💡🔥