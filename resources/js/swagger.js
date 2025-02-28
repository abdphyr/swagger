import SwaggerUI from 'swagger-ui';
import 'swagger-ui/dist/swagger-ui.css';

// IMPORT THIS IN "app.js" file
// Install "npm i swagger-ui"
// Build "npm run build"

SwaggerUI({
    dom_id: "#swagger", // HTML ROOT ELEMENT ID in the "swagger.blade.php" in views section 
    url: "/swagger-document" //DON'T CHANGE THIS. To see this api endpoint run "php artisan route:list" or open "http://localhost:8000/swagger-document"
})