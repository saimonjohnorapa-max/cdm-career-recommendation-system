import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
export default defineConfig({
	base: '/cdm-career-recommendation-system/admin/',
	plugins: [vue()],
	server: { port: 5174 },
	build: {
		outDir: 'dist',
		sourcemap: true,
		rollupOptions: {
			output: {
				entryFileNames: 'assets/admin.js',
				chunkFileNames: 'assets/[name].js',
				assetFileNames: 'assets/[name][extname]',
			},
		},
	},
});
