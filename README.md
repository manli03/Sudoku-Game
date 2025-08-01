# Sudoku Web App

## Description

This is a web-based Sudoku game application that supports both single-player and multiplayer modes. Users can register, log in, play Sudoku puzzles, track their game history, and compete with others in real-time.

## Installation

To set up the project locally, follow these steps:

1.  **Prerequisites:**
    *   Web server with PHP support (e.g., Apache, Nginx)
    *   MySQL database
    *   Composer (for PHP dependency management)

2.  **Clone the repository:**
    ```bash
    git clone https://github.com/manli03/Sudoku-Game
    cd "Sudoku-Game"
    ```

3.  **Set up the database:**
    *   Create a MySQL database (e.g., `sudoku_db`).
    *   Import the database schema from `sudoku_db.sql` located in the project root.

4.  **Configure database connection:**
    *   Open `db.php` and update the database connection details (hostname, username, password, database name) to match your setup.

5.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

6.  **Place project in web server directory:**
    *   Move the `sudoku web app` directory to your web server's document root (e.g., `htdocs` for Apache).

## Usage

1.  Open your web browser and navigate to the project's URL (e.g., `http://localhost/sudoku%20web%20app/`).
2.  **Register:** Create a new account if you don't have one.
3.  **Login:** Log in with your credentials.
4.  **Play Solo:** Start a single-player Sudoku game.
5.  **Play Multiplayer:** Join or create a multiplayer lobby to play with friends.
6.  **View History:** Check your past game records.

## Contributing

Contributions are welcome! If you'd like to contribute, please follow these steps:

1.  Fork the repository.
2.  Create a new branch (`git checkout -b feature/your-feature-name`).
3.  Make your changes.
4.  Commit your changes (`git commit -m 'Add some feature'`).
5.  Push to the branch (`git push origin feature/your-feature-name`).
6.  Open a Pull Request.

## License

This project is licensed under the MIT License - see the `LICENSE` file for details.