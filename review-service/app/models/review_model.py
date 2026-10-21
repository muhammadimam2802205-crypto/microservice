
# app/models/review_model.py
# Lapisan model: akses langsung ke MongoDB.

from pymongo import DESCENDING, ReturnDocument

from app.database import get_collection


# Mengubah dokumen MongoDB menjadi data yang siap dijadikan JSON.
def _serialize(doc):
    if doc is None:
        return None

    return {
        "id": str(doc["_id"]),
        "product_id": doc["product_id"],
        "user_id": doc["user_id"],
        "rating": doc["rating"],
        "comment": doc["comment"],
        "created_at": doc["created_at"].isoformat(),
        "updated_at": doc["updated_at"].isoformat(),
    }


# Mengambil semua review untuk satu produk, yang terbaru di atas.
def find_by_product(product_id):
    cursor = get_collection().find(
        {"product_id": product_id}
    ).sort([
        ("created_at", DESCENDING),
        ("_id", DESCENDING),
    ])

    return [_serialize(doc) for doc in cursor]


# Mengambil satu review berdasarkan ObjectId.
def find_by_id(object_id):
    return _serialize(
        get_collection().find_one({"_id": object_id})
    )


# Menyimpan review baru.
def insert(document):
    result = get_collection().insert_one(document)
    return find_by_id(result.inserted_id)


# Memperbarui field review.
def update(object_id, fields):
    doc = get_collection().find_one_and_update(
        {"_id": object_id},
        {"$set": fields},
        return_document=ReturnDocument.AFTER,
    )

    return _serialize(doc)


# Menghapus review.
def delete(object_id):
    return (
        get_collection().delete_one({"_id": object_id}).deleted_count > 0
    )


# Menghitung rata-rata rating dan jumlah review.
def rating_summary(product_id):
    pipeline = [
        {"$match": {"product_id": product_id}},
        {
            "$group": {
                "_id": None,
                "average": {"$avg": "$rating"},
                "total": {"$sum": 1},
            }
        },
    ]

    result = list(get_collection().aggregate(pipeline))

    if not result or not result[0]["total"]:
        return {"average_rating": 0, "total_reviews": 0}

    return {
        "average_rating": round(result[0]["average"], 2),
        "total_reviews": result[0]["total"],
    }
