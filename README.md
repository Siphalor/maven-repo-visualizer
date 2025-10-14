<div align="center">

# Maven Repository Visualizer (MavenRV)

A php directory listing for Maven repositories.

</div>

## Features

- Clean, responsive web interface for Maven repository browsing
- Intelligent sorting of Maven artifacts and versions
- Support for both light and dark themes
- Configurable appearance and behavior

## Installation

### Prerequisites

- PHP 8.1 or higher with PHP-FPM
- Nginx web server
- File access to a Maven repository directory

### Setup

1. **Clone the repository:**  
   ```bash
   git clone https://gitea.siphalor.de/Siphalor/maven-repo-visualizer mavenrv
   cd mavenrv
   ```

2. **Install dependencies:**  
   ```bash
   composer install
   ```

3. **Configure environment:**  
   ```bash
   cp .env.example .env
   ```
   Edit `.env` to customize your installation

4. **Setup nginx configuration:**  
   See [`example.nginx.conf`](example.nginx.conf)
