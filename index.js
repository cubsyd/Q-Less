const express = require('express');
const cors = require('cors');
require('dotenv').config();

const app = express();
const PORT = 3000; // El puerto donde escuchará tu backend

// Configuración de Middlewares
app.use(cors()); // Permite que Angular (Puerto 4200) acceda a estos datos
app.use(express.json()); // Permite que el servidor entienda datos en formato JSON

// Ruta de prueba inicial
app.get('/', (req, res) => {
    res.send('Servidor del Proyecto Final configurado con éxito por Anderson Felipe');
});

// Inicio del servidor
app.listen(PORT, () => {
    console.log(`Servidor corriendo en el puerto http://localhost:${PORT}`);
});