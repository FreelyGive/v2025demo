import clsx from 'clsx';

import styles from './Spotlight.module.css';

type Props = {
  top: number;
  left: number;
  width: number;
  height: number;
  blocking: boolean;
};

export const Spotlight = (props: Props) => {
  const { top, left, width, height, blocking = true } = props;
  return (
    <div
      className={clsx('spotlight', styles.spotlight, {
        [styles.blocking]: blocking,
      })}
    >
      {/*row 1*/}
      <div
        className={styles.position}
        style={{ width: `${left}px`, height: `${top}px` }}
      ></div>
      <div />
      <div />
      {/*row 2*/}
      <div />
      <div
        className={styles.highlighted}
        data-testid="canvas-region-spotlight-highlight"
        style={{ width: `${width}px`, height: `${height}px` }}
      ></div>
      <div />
      {/*row 3*/}
      <div />
      <div />
      <div />
    </div>
  );
};
