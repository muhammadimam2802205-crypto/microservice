
# app/clients/product_client.py
# Klien HTTP untuk berkomunikasi dengan product-service.

import os

import requests

from app.exceptions import ProductServiceUnavailableError

TIMEOUT_SECONDS = 3


def product_exists(product_id):
    # Alamat dibaca ketika fungsi dipanggil.
    base_url = os.getenv(
        "PRODUCT_SERVICE_URL",
        "http://localhost:3000",
    )

    try:
        response = requests.get(
            f"{base_url}/products/{product_id}",
            timeout=TIMEOUT_SECONDS,
        )
    except requests.RequestException as error:
        raise ProductServiceUnavailableError(
            "Product service tidak dapat dihubungi"
        ) from error

    if response.status_code == 404:
        return False

    if not response.ok:
        raise ProductServiceUnavailableError(
            f"Product service membalas error {response.status_code}"
        )

    return True
