import chalk from 'chalk';
import { table } from 'table';
import * as p from '@clack/prompts';

import type { Result } from '../types/Result';

/**
 * Report operation results in a table.
 */
export function reportResults(
  results: Result[],
  title: string,
  itemLabel = 'Component',
): void {
  // Alphabetize results by component name.
  results.sort((a, b) => a.itemName.localeCompare(b.itemName));

  const successful = results.filter((r) => r.success).length;
  const failed = results.filter((r) => !r.success).length;
  const hasDetails = results.some((r) => (r.details?.length ?? 0) > 0);

  const succeededText =
    failed === 0
      ? chalk.green(`${successful} succeeded`)
      : `${successful} succeeded`;
  const failedText =
    failed > 0 ? chalk.red(`${failed} failed`) : chalk.dim(`${failed} failed`);
  const summary = `${succeededText}, ${failedText}`;

  if (results.length > 0) {
    const tableData = [
      hasDetails ? [chalk.bold(title), '', ''] : [chalk.bold(title), ''],
      hasDetails ? [itemLabel, 'Status', 'Details'] : [itemLabel, 'Status'],
      ...results.map((r) =>
        hasDetails
          ? [
              r.itemName,
              r.success ? chalk.green('Success') : chalk.red('Failed'),
              r.details
                ?.map((d) =>
                  d.heading
                    ? `${chalk.underline(d.heading)}:\n${d.content}`
                    : d.content,
                )
                .join('\n\n'),
            ]
          : [
              r.itemName,
              r.success ? chalk.green('Success') : chalk.red('Failed'),
            ],
      ),
      hasDetails ? ['SUMMARY', '', summary] : ['SUMMARY', summary],
    ];
    p.log.info(
      table(tableData, {
        spanningCells: [
          {
            row: 0,
            col: 0,
            colSpan: hasDetails ? 3 : 2,
            alignment: 'center',
          },
          {
            row: results.length + 2,
            col: 0,
            colSpan: hasDetails ? 2 : 1,
            alignment: hasDetails ? 'right' : 'left',
          },
        ],
      }),
    );
  }
}
