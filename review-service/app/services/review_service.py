
# app/services/review_service.py
# Lapisan service: tempat logika bisnis review.

from datetime import datetime, timezone

from bson import ObjectId
from pymongo.errors import DuplicateKeyError

from app.clients import product_client
from app.exceptions import (
    DuplicateReviewError,
    ProductNotFoundError,
    ReviewNotFoundError,
)
from app.models import review_model


# Mengubah ID teks dari URL menjadi ObjectId.
def _parse_id(review_id):
    if not ObjectId.is_valid(review_id):
        raise ReviewNotFoundError(review_id)

    return ObjectId(review_id)


# Mengambil semua review satu produk beserta ringkasannya.
def get_product_reviews(product_id):
    return {
        "product_id": product_id,
        "summary": review_model.rating_summary(product_id),
        "reviews": review_model.find_by_product(product_id),
    }


# Mengambil satu review berdasarkan ID.
def get_review(review_id):
    review = review_model.find_by_id(_parse_id(review_id))

    if review is None:
        raise ReviewNotFoundError(review_id)

    return review


# Membuat review baru.
def create_review(data):
    if not product_client.product_exists(data["product_id"]):
        raise ProductNotFoundError(data["product_id"])

    now = datetime.now(timezone.utc)
    document = {
        **data,
        "created_at": now,
        "updated_at": now,
    }

    try:
        return review_model.insert(document)
    except DuplicateKeyError:
        raise DuplicateReviewError(
            data["user_id"],
            data["product_id"],
        )


# Memperbarui review.
def update_review(review_id, data):
    fields = {
        **data,
        "updated_at": datetime.now(timezone.utc),
    }

    review = review_model.update(_parse_id(review_id), fields)

    if review is None:
        raise ReviewNotFoundError(review_id)

    return review


# Menghapus review.
def delete_review(review_id):
    if not review_model.delete(_parse_id(review_id)):
        raise ReviewNotFoundError(review_id)
