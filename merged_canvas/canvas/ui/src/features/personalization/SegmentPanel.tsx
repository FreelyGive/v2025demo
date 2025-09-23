import { Box } from '@radix-ui/themes';

export default function SegmentPanel({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <Box width="100%" p="6">
      {children}
    </Box>
  );
}
