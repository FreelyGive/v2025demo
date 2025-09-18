/**
 * HelloWorld example component
 */
const HelloWorld = ({
  greeting = 'Hello world!',
  cta = 'Click me!',
  ctaRef = 'https://example.com',
  content,
}) => {
  return (
    <div className="hello-world-component">
      <h2 className="greeting">{greeting}</h2>
      <div className="content">{content}</div>
      <button type="button" className="cta">
        <a href={ctaRef}>{cta}</a>
      </button>
    </div>
  );
};

export default HelloWorld;
