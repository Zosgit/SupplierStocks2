<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\StockItem;
use Psr\Log\LoggerInterface;

final class StockController extends AbstractController
{
    #[Route('/stock', name: 'app_stock')]
    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/StockController.php',
        ]);
    }

    #[Route('/get-stocks', name: 'get_stocks', methods: ['GET'])]
    public function getStocks(Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
    {
        $logger->info('getStocks() called', ['query' => $request->query->all()]);

        // Get query parameters from the URL
        $ean = $request->query->get('ean');
        $mpn = $request->query->get('mpn');

        // If neither parameter is provided, return a 400 error
        if (empty($ean) && empty($mpn)) {
            return $this->json([
                'error' => 'Please provide at least one query parameter: ean or mpn'
            ], 400);
        }

        // Build search query based on given parameters (one or both)
        $qb = $em->createQueryBuilder();
        $qb->select('s')
           ->from(StockItem::class, 's');

        if (!empty($ean) && !empty($mpn)) {
            $qb->where('s.ean = :ean OR s.mpn = :mpn')
               ->setParameter('ean', $ean)
               ->setParameter('mpn', $mpn);
        } elseif (!empty($ean)) {
            $qb->where('s.ean = :ean')
               ->setParameter('ean', $ean);
        } elseif (!empty($mpn)) {
            $qb->where('s.mpn = :mpn')
               ->setParameter('mpn', $mpn);
        }

        $qb->orderBy('s.id', 'ASC');
        $items = $qb->getQuery()->getResult();

        // Prepare formatted array for JSON output
        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'id' => $item->getId(),
                'external_id' => $item->getExternalId(),
                'mpn' => $item->getMpn(),
                'producer_name' => $item->getProducerName(),
                'ean' => $item->getEan(),
                'price' => $item->getPrice(),
                'quantity' => $item->getQuantity(),
            ];
        }

        $logger->info('Stock items successfully retrieved', ['records_count' => count($data)]);

        // Encode data to JSON with multi-line output
        $json = json_encode(
            [
                'total_returned' => count($data),
                'items' => $data,
            ],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        // Build HTTP response with proper headers
        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Cache-Control', 'no-cache, private');
        $response->headers->set('Content-Length', strlen($json));

        // Ensure the response is fully sent before FastCGI closes the connection
        $response->sendHeaders();
        $response->sendContent();
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $logger->info('Response successfully sent to client', ['content_length' => strlen($json)]);

        return $response;
    }
}
