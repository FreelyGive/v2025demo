import { forwardRef, useRef } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';

import { a2p } from '@/local_packages/utils.js';

import type { Editor } from '@ckeditor/ckeditor5-core';
import type { Attributes } from '@/types/DrupalAttribute';

// Type definitions for CKEditor
interface RegExpConfig {
  regexp: {
    pattern: string;
  };
}

interface FuncConfig {
  func: {
    name: string;
    invoke?: boolean;
    args?: any[];
  };
}

interface EditorInstance extends Editor {
  getData: () => string;
}

interface DrupalFormattedTextAreaProps {
  attributes?: Attributes;
  format: {
    editorSettings: {
      toolbar: any[];
      plugins: string[];
      config: ConfigObject;
      language: Record<string, any>;
    };
  };
}

type ConfigValue = RegExpConfig | FuncConfig | ConfigObject | any[] | primitive;
type primitive = string | number | boolean | null | undefined;

interface ConfigObject {
  [key: string]: ConfigValue;
}

// Below are several functions borrowed from core's ckeditor5.js.
// @todo use core versions after landing https://www.drupal.org/i/3521761
function buildRegexp(config: RegExpConfig): RegExp {
  const { pattern } = config.regexp;

  const main = pattern.match(/\/(.+)\/.*/)?.[1] || '';
  const options = pattern.match(/\/.+\/(.*)/)?.[1] || '';

  return new RegExp(main, options);
}

function findFunc(scope: any, name: string): Function | null {
  if (!scope) {
    return null;
  }
  const parts = name.includes('.') ? name.split('.') : [name];

  if (parts.length > 1) {
    return findFunc(scope[parts.shift()!], parts.join('.'));
  }
  return typeof scope[parts[0]] === 'function' ? scope[parts[0]] : null;
}

function buildFunc(config: FuncConfig): any {
  const { func } = config;
  // Assuming a global object.
  const fn = findFunc(window, func.name);
  if (typeof fn === 'function') {
    const result = func.invoke ? fn(...(func.args || [])) : fn;
    return result;
  }
  return null;
}

function processConfig(
  config: ConfigObject | null,
): Record<string, any> | null {
  /**
   * Processes an array in config recursively.
   *
   * @param config - An array that should be processed recursively.
   * @return An array that has been processed recursively.
   */
  function processArray(config: any[]): any[] {
    return config.map((item) => {
      if (typeof item === 'object' && item !== null) {
        return processConfig(item as ConfigObject);
      }

      return item;
    });
  }

  if (config === null) {
    return null;
  }

  return Object.entries(config).reduce<Record<string, any>>(
    (processed, [key, value]) => {
      if (typeof value === 'object' && value !== null) {
        // Check for null values.
        if (!value) {
          return processed;
        }
        if (Object.prototype.hasOwnProperty.call(value, 'func')) {
          processed[key] = buildFunc(value as FuncConfig);
        } else if (Object.prototype.hasOwnProperty.call(value, 'regexp')) {
          processed[key] = buildRegexp(value as RegExpConfig);
        } else if (Array.isArray(value)) {
          processed[key] = processArray(value);
        } else {
          processed[key] = processConfig(value as ConfigObject);
        }
      } else {
        processed[key] = value;
      }

      return processed;
    },
    {},
  );
}

/**
 * Select CKEditor 5 plugin classes to include.
 *
 * Found in the CKEditor 5 global JavaScript object as {package.Class}.
 *
 * @param plugins - List of package and Class name of plugins
 * @return List of JavaScript Classes to add in the extraPlugins property of config.
 */
function selectPlugins(plugins: string[]): any[] {
  return plugins.map((pluginDefinition) => {
    const [build, name] = pluginDefinition.split('.');
    // Define a more specific type for window.CKEditor5
    const ckEditor = (window as any).CKEditor5;
    if (ckEditor?.[build] && ckEditor?.[build]?.[name]) {
      return ckEditor[build][name];
    }

    console.warn(`Failed to load ${build} - ${name}`);
    return null;
  });
}
// This concludes the functions borrowed from core's ckeditor5.js.

const DrupalFormattedTextArea = forwardRef<
  HTMLTextAreaElement,
  DrupalFormattedTextAreaProps
>(function DrupalFormattedTextArea({ attributes = {}, format }, ref) {
  const editorRef = useRef<EditorInstance | null>(null);
  const dataRef = useRef<string | null>(null);

  const { toolbar, plugins, config, language } = format.editorSettings;
  const extraPlugins = selectPlugins(plugins);
  const pluginConfig = processConfig(config) || {};
  const editorConfig = {
    extraPlugins,
    toolbar,
    ...pluginConfig,
    language: { ...(pluginConfig.language || {}), ...language },
    initialData: (dataRef?.current || attributes.value?.toString()) ?? '',
  };
  const { editorClassic } = window.CKEditor5;
  const { ClassicEditor } = editorClassic;

  return (
    <>
      <CKEditor
        editor={ClassicEditor}
        config={editorConfig}
        onReady={(editor) => {
          editorRef.current = editor as EditorInstance;

          // If the rows attribute is present, let that inform the editor's
          // minimum height
          if (attributes.rows && Number(attributes.rows)) {
            const editable = editor.ui.view.editable.element;
            if (editable) {
              const editorElement = editable.closest('.ck-editor');
              if (editorElement instanceof HTMLElement) {
                editorElement.style.setProperty(
                  '--ck-min-height',
                  `${Number(attributes.rows) * 20}px`,
                );
              }
            }
          }
        }}
        onChange={function () {
          if (!editorRef.current) {
            return;
          }
          // Get the editor contents and update the textarea that is synced with
          // Redux.
          const data = editorRef.current.getData();
          dataRef.current = data;
          const textareaElement = ref && 'current' in ref ? ref.current : null;
          if (textareaElement) {
            const changeEvent = new Event('change');
            textareaElement.value = data;
            textareaElement.innerHTML = data;
            Object.defineProperty(changeEvent, 'target', {
              writable: false,
              value: textareaElement,
            });
            if (typeof attributes?.onChange === 'function') {
              attributes.onChange(changeEvent);
            }
          }
        }}
      />
      {/* This is a hidden textarea that is synced with Redux. */}
      <textarea
        {...a2p(attributes, {}, { skipAttributes: ['value', 'onChange'] })}
        ref={ref}
        style={{ display: 'none' }}
        value={attributes.value?.toString() ?? ''}
        onChange={attributes.onChange}
      ></textarea>
    </>
  );
});

export default DrupalFormattedTextArea;
