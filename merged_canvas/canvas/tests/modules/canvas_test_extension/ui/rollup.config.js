import commonjs from '@rollup/plugin-commonjs';
import resolve from '@rollup/plugin-node-resolve';
import babel from '@rollup/plugin-babel';
import replace from "@rollup/plugin-replace";
import livereload from 'rollup-plugin-livereload';
// import another from '../../../../ui/node_modules/react'
export default {
  input: 'index.jsx',
  output: {
    file: './dist/bundle.js',
    format: 'umd',
    globals: {
      react: 'React',
      'react-dom': 'ReactDom',
      '@reduxjs/toolkit': 'ReduxToolkit',
      'react-redux': 'ReactRedux'
    }
  },
  external: [
    'react',
    'react-dom',
  ],
  plugins: [
    resolve({
      extensions: ['.js', '.jsx'],
      browser: true
    }),
    livereload(),
    replace({
      preventAssignment: true,
      'process.env.NODE_ENV': JSON.stringify('dev')
    }),

    commonjs({
      transformMixedEsModules: true,
      ignoreDynamicRequires: true,
    }),

    babel({
      exclude: 'node_modules/**',
      presets: [
        ['@babel/preset-react', {"runtime": "automatic"}]
      ],
      babelHelpers: 'bundled',
    }),
  ],
};
