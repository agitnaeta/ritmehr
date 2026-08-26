import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',      // admin — dimuat lewat vite_styles config Backpack
                'resources/css/portal.css',   // portal karyawan — dimuat langsung di layout-nya
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
