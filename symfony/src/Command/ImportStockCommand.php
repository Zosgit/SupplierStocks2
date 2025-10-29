<?php

namespace App\Command;

use App\Entity\StockItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:stock',
    description: 'Import CSV stock data from supplier'
)]
class ImportStockCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Absolute path to CSV file') // e.g. /app/data/lorotom.csv
            ->addArgument('supplier', InputArgument::REQUIRED, 'Supplier name (lorotom/trah)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $file     = (string) $input->getArgument('file');
        $supplier = strtolower((string) $input->getArgument('supplier'));

        // Validate file existence
        if (!is_file($file)) {
            $io->error("File not found: $file");
            return Command::FAILURE;
        }

        $count = 0;
        $h = fopen($file, 'r');
        if (!$h) {
            $io->error("Cannot open file: $file");
            return Command::FAILURE;
        }
        
        /**
         * Supplier: lorotom
         * Format overview:
         *   - Separator: TAB or multiple spaces
         *   - Header: our_code, producer_code, name, producer, quantity, price, ean
         *   - Price: may contain commas (e.g. "87,00")
         *   - Replace quantity ">30" with "31"
         */
        if ($supplier === 'lorotom') {
            $io->section('DEBUG: reading lorotom file...');

            $headerLine = fgets($h);
            if ($headerLine === false) {
                $io->warning('Empty file or header missing.');
                return Command::SUCCESS;
            }

            $header = array_map('trim', $this->smartSplit($headerLine));

            $buffer = '';
            while (($line = fgets($h)) !== false) {
                $line = trim($line, "\r\n");
                if ($line === '') {
                    continue;
                }

                // Concatenate line fragments if a record is broken across lines
                $buffer .= ($buffer === '' ? '' : ' ') . $line;
                $row = $this->smartSplit($buffer);

                // Join further lines until we have at least 6 fields
                if (count($row) < 6) {
                    continue;
                }

                // If there are exactly 6 fields (missing EAN) = null
                if (count($row) === 6) {
                    $row[] = null;
                }

                // Cut excess columns to expected size - 7
                if (count($row) > 7) {
                    $row = array_slice($row, 0, 7);
                }

                // Reset buffer for next iteration
                $buffer = '';

                // Map parsed columns
                $data = [
                    'our_code'      => $row[0],
                    'producer_code' => $row[1],
                    'name'          => $row[2],
                    'producer'      => $row[3],
                    'quantity'      => $row[4],
                    'price'         => $row[5],
                    'ean'           => $row[6] ?? null,
                ];

                // Cleanup and normalize values
                $data     = array_map([$this, 'cleanValue'], $data);
                $quantity = $this->normalizeQuantity($data['quantity'], 31);// >30 => 31
                $price    = $this->normalizePrice($data['price']);

                // Build StockItem entity
                $item = (new StockItem())
                    ->setExternalId($data['our_code'])
                    ->setMpn($data['producer_code'])
                    ->setProducerName($data['producer'])
                    ->setQuantity($quantity)
                    ->setPrice($price)
                    ->setEan($this->normalizeNullable($data['ean'] ?? null));

                $this->em->persist($item);
                $count++;
            }
        } 
        /**
         * Supplier: trah
         * Format overview:
         *   - Separator: semicolon (;)
         *   - No header row
         *   - Columns: 0 external_id | 1 quantity | 2 price | 3 mpn | 4 ean | 5 producer_name
         *   - Replace quantity ">10" with "11"
         *   - Skip rows where producer_name == "NARZEDZIA WARSZTAT" (column "5")
         */
        elseif ($supplier === 'trah') {
            $io->section('DEBUG: reading trah file...');

             while (($line = fgets($h)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $row = $this->smartSplit($line);

                // Each valid record must have at least 6 columns
                if (count($row) < 6) {
                    continue;
                }

                // Map and normalize
                $externalId   = $this->cleanValue($row[0]);
                $quantity     = $this->normalizeQuantity($row[1], 11); // >10 => 11
                $price        = $this->normalizePrice($row[2]);
                $mpn          = $this->cleanValue($row[3]);
                $ean          = $this->normalizeNullable($row[4] ?? null);
                $producerName = $this->cleanValue($row[5]);

                // Skip unwanted category
                if (mb_strtoupper($producerName, 'UTF-8') === 'NARZEDZIA WARSZTAT') {
                    continue;
                }

                // Build entity
                $item = (new StockItem())
                    ->setExternalId($externalId)
                    ->setMpn($mpn)
                    ->setProducerName($producerName)
                    ->setQuantity($quantity)
                    ->setPrice($price)
                    ->setEan($ean);

                $this->em->persist($item);
                $count++;
            }

        } else {
            $io->error("Unknown supplier: $supplier");
            fclose($h);
            return Command::FAILURE;
        }

        fclose($h);
        $this->em->flush();

        $io->success("Imported $count records from $supplier");
        return Command::SUCCESS;
    }

    /**
     * Split a line into fields depending on file format
     * Handles multiple CSV styles:
     *   - Lorotom - tab or multiple spaces
     *   - Trah - semicolon + optional quotes
     */
    private function smartSplit(string $line): array
    {
        // Trim whitespace
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
        $line = trim($line);

        // Lorotom
        if (str_contains($line, "\t")) {
            return array_map('trim', preg_split('/\t+/', $line, -1, PREG_SPLIT_NO_EMPTY));
        }

        // Trah
        if (str_contains($line, ';')) {
            return array_map('trim', str_getcsv($line, ';', '"'));
        }

        return array_map('trim', preg_split('/\s{2,}/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Normalize price to float string with dot as decimal separator
     * Removes unwanted characters and ensures two decimal places
     */
    private function normalizePrice(string $priceRaw): string
    {
        $price = str_replace(',', '.', trim($priceRaw));
        $price = preg_replace('/[^0-9.]/', '', $price) ?? '0';
        return number_format((float)$price, 2, '.', '');
    }

    /**
     * Normalize quantity.
     * - Converts ">10" and ">30" into maximum value
     * - Removes non-numeric characters
     * - Defaults to 0 if invalid
     */
    private function normalizeQuantity(?string $value, int $max = 31): int
    {
        if ($value === null) {
            return 0;
        }

        $value = trim($value);

        // If the value starts with ">", replace with max
        if (str_starts_with($value, '>')) {
            return $max;
        }

        // Remove non-digit characters
        $num = preg_replace('/[^0-9]/', '', $value);

        return ($num === '' || $num === null) ? 0 : (int)$num;
    }

    /**
     * Clean value by trimming spaces, tabs, quotes
     */
    private function cleanValue(?string $v): string
    {
        if ($v === null) {
            return '';
        }
        return trim($v, " \t\n\r\0\x0B\"'");
    }

    /**
     * Normalize optional value
     * Converts empty or 'null' strings into real null
     */
    private function normalizeNullable(?string $v): ?string
    {
        $v = $v !== null ? trim($v, " \t\n\r\0\x0B\"'") : null;
        if ($v === '' || strtolower($v ?? '') === 'null') {
            return null;
        }
        return $v;
    }
}
