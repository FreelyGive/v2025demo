const Video = ({
  title = '<h3>Video</h3>',
  video = { src: null, poster: null },
  text,
}) => {
  const { src, poster } = video;
  return (
    <>
      {src && <video controls src={src} poster={poster}></video>}
      <p>{text}</p>
    </>
  );
};

export default Video;
