<?php
ob_clean();  // Clean (erase) the output buffer to prevent any previous output
header("Content-Type: text/xml; charset=UTF-8");

// Function to fetch Reddit Atom feed
function fetchRedditFeed($subreddit) {
    $url = "https://www.reddit.com/r/$subreddit/.rss";
    $context = stream_context_create(['http' => ['user_agent' => 'Mozilla/5.0']]);
    return file_get_contents($url, false, $context);
}

// Get subreddit from URL
$subreddit = $_GET['subreddit'] ?? '';
if (!$subreddit) {
    header("HTTP/1.1 400 Bad Request");
    echo "<error>Missing 'subreddit' parameter.</error>";
    exit;
}

// Fetch Reddit feed
$atomXML = fetchRedditFeed($subreddit);
if (!$atomXML) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<error>Error fetching feed.</error>";
    exit;
}

// Load Atom XML
$atom = new SimpleXMLElement($atomXML);

// Create MediaRSS structure
$rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"></rss>');
$rss->addAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');

// Create the channel node
$channel = $rss->addChild('channel');
$channel->addChild('title', $atom->title);
$channel->addChild('link', $atom->id);
$channel->addChild('description', "Reddit MediaRSS Feed for r/$subreddit");
$channel->addChild('lastBuildDate', date(DATE_RSS));

// Convert Atom entries to MediaRSS items
foreach ($atom->entry as $entry) {
    $item = $channel->addChild('item');
    $item->addChild('title', htmlspecialchars($entry->title));
    $item->addChild('link', $entry->link['href']);
    $item->addChild('guid', $entry->id);
    $item->addChild('pubDate', date(DATE_RSS, strtotime($entry->updated)));
    $item->addChild('description', htmlspecialchars($entry->content));

    // Extract image URL from <a> tag followed by [link]
    $content = (string) $entry->content;
    
    // Refined regex: Match <a> href="https://i.redd.it/...">[link]</a>
    $imageUrl = '';
    if (preg_match('/<a href="(https:\/\/i\.redd\.it\/[^"]+)"\s*>\[link\]<\/a>/', $content, $matches)) {
        $imageUrl = $matches[1];  // Extract image URL directly
    }

    // If the image URL contains query parameters (e.g., ?width=640&crop=smart), remove them
    if ($imageUrl) {
        $imageUrl = preg_replace('/\?.*/', '', $imageUrl);  // Strip out query parameters
    }

    // Debugging: Check if the pattern matches
    if ($imageUrl) {
        // This will show up in your XML output as a comment
        $item->addChild('comment', "<!-- Image URL Found: $imageUrl -->");
    } else {
        $item->addChild('comment', "<!-- No Image URL Match for this item -->");
    }

    // Add the enclosure tag (used for attachments like images)
    if ($imageUrl) {
        $enclosure = $item->addChild('enclosure');
        $enclosure->addAttribute('url', $imageUrl);  // Add the image URL
        $enclosure->addAttribute('length', '0'); // You can adjust the length if you have it
        $enclosure->addAttribute('type', 'image/jpeg'); // Set image type, adjust if necessary
    }
}

// Output the final RSS
echo $rss->asXML();
?>
