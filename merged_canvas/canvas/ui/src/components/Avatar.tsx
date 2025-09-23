import * as Avatar from '@radix-ui/react-avatar';

import styles from './Avatar.module.css';

const AvatarComponent = ({
  imageUrl,
  name,
}: {
  name: string;
  imageUrl?: string;
}) => {
  const initial = name.trim().charAt(0).toUpperCase();
  return (
    <Avatar.Root className={styles.root}>
      {imageUrl && <Avatar.Image className={styles.image} src={imageUrl} />}
      {!imageUrl && (
        <Avatar.Fallback className={styles.fallback}>{initial}</Avatar.Fallback>
      )}
    </Avatar.Root>
  );
};

export default AvatarComponent;
