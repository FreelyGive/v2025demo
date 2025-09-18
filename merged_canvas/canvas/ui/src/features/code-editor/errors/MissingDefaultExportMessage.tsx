import { Code, Flex, Text } from '@radix-ui/themes';

import styles from './error.module.css';

const MissingDefaultExportMessage = () => {
  return (
    <Flex direction="column" gap="3">
      <TextBlock>If your component uses function declaration syntax:</TextBlock>
      <CodeBlock>
        {`function MyComponent() {\n  return (\n    <div>\n      Hello world\n    </div>\n  );\n}`}
      </CodeBlock>

      <TextBlock>Add "export default" in front, like so:</TextBlock>
      <CodeBlock>
        {`export default function MyComponent() {\n  return (\n    <div>\n      Hello world\n    </div>\n  );\n}`}
      </CodeBlock>

      <TextBlock>Or if it uses the arrow function syntax:</TextBlock>
      <CodeBlock>
        {`const MyComponent = () => {\n  return (\n    <div>\n      Hello world\n    </div>\n  );\n};`}
      </CodeBlock>

      <TextBlock>
        Add "export default" and the name of your component at the end:
      </TextBlock>
      <CodeBlock>
        {`const MyComponent = () => {\n  return (\n    <div>\n      Hello world\n    </div>\n  );\n};\nexport default MyComponent;`}
      </CodeBlock>
    </Flex>
  );
};

export default MissingDefaultExportMessage;

export const TextBlock = ({ children }: { children: React.ReactNode }) => {
  return (
    <Text as="p" size="2" mt="2">
      {children}
    </Text>
  );
};

export const CodeBlock = ({ children }: { children: React.ReactNode }) => {
  return (
    <Code size="2" className={styles.code}>
      {children}
    </Code>
  );
};
