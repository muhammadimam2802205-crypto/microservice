const productModel = require('../models/productModel');

// Helper function untuk validasi Base64 Image
function validateBase64Image(image) {
    if (!image || typeof image !== 'string' || image.trim() === '') {
        return { valid: false, message: "Field image wajib diisi" };
    }

    let cleanBase64 = image.includes(',') ? image.split(',')[1] : image;

    const base64Regex = /^[A-Za-z0-9+/]+={0,2}$/;
    if (cleanBase64.length % 4 !== 0 || !base64Regex.test(cleanBase64)) {
        return { valid: false, message: "Field image harus berupa Base64 yang valid" };
    }

    const imageBuffer = Buffer.from(cleanBase64, 'base64');
    const maxSizeInBytes = 2 * 1024 * 1024;

    if (imageBuffer.length > maxSizeInBytes) {
        return { valid: false, message: "Ukuran image maksimal 2 MB" };
    }

    return { valid: true, cleanBase64: cleanBase64 };
}

// GET - Ambil semua product
async function index(req, res) {
    try {
        const products = await productModel.getAllProducts();
        res.status(200).json({ 
            message: "Berhasil mengambil data product", 
            data: products
        });
    } catch (error) {
        res.status(500).json({
            message: "Gagal mengambil data product",
            error: error.message
        });
    }
}

// GET - Ambil product berdasarkan ID
async function getById(req, res) {
    try {
        const { id } = req.params;
        const product = await productModel.getProductById(id);

        if (!product) {
            return res.status(404).json({
                message: "Product tidak ditemukan"
            });
        }

        res.status(200).json({
            message: "Berhasil mengambil detail product",
            data: product
        });
    } catch (error) {
        res.status(500).json({
            message: "Gagal mengambil detail product",
            error: error.message
        });
    }
}

// POST - Tambah product baru
async function create(req, res) {
    try {
        const { name, description, price, stock, image } = req.body;

        const imageValidation = validateBase64Image(image);
        if (!imageValidation.valid) {
            return res.status(400).json({
                message: imageValidation.message
            });
        }

        if (!name || !price) {
            return res.status(400).json({
                message: "Name dan price wajib diisi"
            });
        }

        const insertId = await productModel.createProduct({ 
            name, 
            description, 
            price, 
            stock, 
            image: imageValidation.cleanBase64
        });
        const newProduct = await productModel.getProductById(insertId);

        res.status(201).json({
            message: "Berhasil menambahkan product baru",
            data: newProduct
        });
    } catch (error) {
        res.status(500).json({
            message: "Gagal menambahkan product",
            error: error.message
        });
    }
}

// PUT - Update product berdasarkan ID
async function update(req, res) {
    try {
        const { id } = req.params;
        const { name, description, price, stock, image } = req.body;

       const existingProduct = await productModel.getProductById(id);
        if (!existingProduct) {
            return res.status(404).json({
                message: "Product tidak ditemukan"
            });
        }

        // Validasi image jika dikirim/di-update
        let updatedImage = existingProduct.image;
        if (image !== undefined) {
            const validation = validateBase64Image(image);
            if (!validation.valid) {
                return res.status(400).json({
                    message: validation.message
                });
            }
            updatedImage = validation.cleanBase64;
        }

        const updatedData = {
            name: name !== undefined ? name : existingProduct.name,
            description: description !== undefined ? description : existingProduct.description,
            price: price !== undefined ? price : existingProduct.price,
            stock: stock !== undefined ? stock : existingProduct.stock,
            image: updatedImage
        };

        const product = await productModel.updateProduct(id, updatedData);

        res.status(200).json({
            message: "Berhasil memperbarui data product",
            data: product
        });
    } catch (error) {
        res.status(500).json({
            message: "Gagal memperbarui product",
            error: error.message
        });
    }
}

// DELETE - Hapus product berdasarkan ID
async function destroy(req, res) {
    try {
        const { id } = req.params;
        const existingProduct = await productModel.getProductById(id);

        if (!existingProduct) {
            return res.status(404).json({
                message: "Product tidak ditemukan"
            });
        }

        await productModel.deleteProduct(id);

        res.status(200).json({
            message: "Berhasil menghapus product",
            data: existingProduct
        });
    } catch (error) {
        res.status(500).json({
            message: "Gagal menghapus product",
            error: error.message
        });
    }
}

module.exports = {
    index,
    getById,
    create,
    update,
    destroy
};