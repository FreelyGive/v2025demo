type FormattedTextProps = {
  children: string;
  as?: 'div' | 'span';
  className?: string;
  id?: string;
  style?: Record<string, string>;
  [key: string]: any;
};

export default function FormattedText({
  children,
  as = 'div',
  ...props
}: FormattedTextProps) {
  const Component = as;
  return (
    <Component dangerouslySetInnerHTML={{ __html: children }} {...props} />
  );
}
