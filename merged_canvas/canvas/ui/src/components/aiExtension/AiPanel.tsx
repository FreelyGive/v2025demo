import { Box } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import { selectAiPanelOpen } from '@/features/ui/primaryPanelSlice';

import AiWizard from './AiWizard';

import styles from './AiPanel.module.css';

interface AiPanelProps {}

const AiPanel: React.FC<AiPanelProps> = () => {
  const isOpen = useAppSelector(selectAiPanelOpen);
  return (
    <Box
      className={styles.aiPanel}
      data-open={isOpen}
      data-testid="canvas-ai-panel"
    >
      <div data-open={isOpen} className={styles.aiPanelContent}>
        {isOpen && <AiWizard />}
      </div>
    </Box>
  );
};

export default AiPanel;
