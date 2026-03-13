<?php

namespace App\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/joindin')]
class JoindInController extends AbstractController
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.joind.in/v2.1/',
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'AlbumReviews/1.0',
            ],
            'timeout' => 10,
            'verify' => false,
        ]);
    }

    /**
     * Display a list of past events from JoindIn.
     * Each event has links to view its description and talks.
     */
    #[Route('/events', name: 'joindin_events', methods: ['GET'])]
    public function eventsAction(): Response
    {
        $events = [];
        $error = null;

        try {
            $response = $this->client->request('GET', 'events', [
                'query' => [
                    'filter' => 'past',
                    'resultsperpage' => 20,
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            //echo $body; // Debugging
            // foreach ($data as $event) {
            //     echo $event['name'] . "\n"; // Debugging
            // }
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Failed to parse JSON response: ' . json_last_error_msg();
            } else {
                $events = $data['events'] ?? [];
            }

        } catch (GuzzleException $e) {
            $error = 'Failed to fetch events from JoindIn API: ' . $e->getMessage();
        } catch (\Exception $e) {
            $error = 'Unexpected error: ' . $e->getMessage();
        }

        if ($error) {
            $this->addFlash('error', $error);
        }

        return $this->render('joindin/events.html.twig', [
            'events' => $events,
        ]);
    }

    /**
     * Display the name, date, and description of a specific event.
     */
    #[Route('/event/{eventId}', name: 'joindin_event', methods: ['GET'])]
    public function eventAction(int $eventId): Response
    {
        try {
            $response = $this->client->get("events/{$eventId}");

            $data = json_decode($response->getBody()->getContents(), true);
            $event = $data['events'][0] ?? null;

        } catch (GuzzleException $e) {
            $event = null;
            $this->addFlash('error', 'Failed to fetch event details from JoindIn API: ' . $e->getMessage());
        }

        return $this->render('joindin/event.html.twig', [
            'event' => $event,
        ]);
    }

    /**
     * Display the list of talks from a specific event.
     * Shows talk titles, abstracts, and average ratings.
     */
    #[Route('/event/{eventId}/talks', name: 'joindin_talks', methods: ['GET'])]
    public function talksAction(int $eventId): Response
    {
        $event = null;
        $talks = [];

        try {
            // First get the event details to show the event name
            $eventResponse = $this->client->get("events/{$eventId}");
            $eventData = json_decode($eventResponse->getBody()->getContents(), true);
            $event = $eventData['events'][0] ?? null;

            // Then get the talks for this event
            $talksResponse = $this->client->get("events/{$eventId}/talks");
            $talksData = json_decode($talksResponse->getBody()->getContents(), true);
            $talks = $talksData['talks'] ?? [];

        } catch (GuzzleException $e) {
            $this->addFlash('error', 'Failed to fetch talks from JoindIn API: ' . $e->getMessage());
        }

        return $this->render('joindin/talks.html.twig', [
            'event' => $event,
            'talks' => $talks,
        ]);
    }
}
