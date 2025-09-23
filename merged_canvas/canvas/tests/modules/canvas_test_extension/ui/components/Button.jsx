const Button = (props) => {
  return <button
    style={{
      border: '1px solid black',
      backgroundColor: '#e6fafa',
      padding: '.25rem',
      margin: '.1rem .2rem',
      fontSize: '12px',
      cursor: 'pointer',
    }}
    {...props}
  >
    {props.children}
  </button>
}

export default Button;
