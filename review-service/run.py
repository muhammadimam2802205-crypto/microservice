
# run.py
# Titik masuk untuk menjalankan aplikasi.

import os

from app import create_app

app = create_app()

if __name__ == "__main__":
    app.run(
        host=os.getenv("HOST", "127.0.0.1"),
        port=int(os.getenv("PORT", "3002")),
        debug=app.debug,
    )
