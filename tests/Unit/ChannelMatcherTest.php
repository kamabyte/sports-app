<?php

use App\Guide\Listings\ChannelMatcher;
use App\Models\Channel;

/** Канал как из Dispatcharr — без базы. */
function channelNamed(int $id, string $name, ?string $tvgId = null): Channel
{
    $channel = new Channel(['name' => $name, 'tvg_id' => $tvgId]);
    $channel->id = $id;

    return $channel;
}

/** Выбор канала телегида при такой программе у всех кандидатов. */
function chosenGuide(Channel $channel, array $guides, array $stats = [], array $anyCountry = []): ?array
{
    $matcher = new ChannelMatcher;
    $candidates = $matcher->candidates(collect([$channel]), $guides, $anyCountry);
    $real = ['programmes' => 20, 'titles' => 10];

    foreach ($guides as $source => $channels) {
        foreach (array_keys($channels) as $id) {
            $stats[$source][$id] ??= $real;
        }
    }

    return $matcher->choose($candidates[$channel->id], $stats, array_keys($guides));
}

it('finds a channel by its tvg-id without the stream variant', function (): void {
    expect(chosenGuide(channelNamed(1, 'Матч! Арена', '2003'), ['epg_one' => ['2003' => ['Матч Арена HD']]]))
        ->toBe(['source' => 'epg_one', 'channel' => '2003'])
        ->and(chosenGuide(channelNamed(1, 'Sport1', 'Sport1.cz@SD'), ['cz' => ['Sport1.cz' => ['Sport 1']]]))
        ->toBe(['source' => 'cz', 'channel' => 'Sport1.cz']);
});

it('matches by name within the country of the tvg-id', function (): void {
    $guides = ['hu' => ['Sport1.hu' => ['Sport1']], 'cz' => ['Sport1.(hu).cz' => ['Sport1']]];

    expect(chosenGuide(channelNamed(1, 'Sport1', 'Sport1Old.cz@SD'), $guides))->toBe(['source' => 'cz', 'channel' => 'Sport1.(hu).cz'])
        // Канал другой страны — только в телегидах без страны (FAST-сервисы).
        ->and(chosenGuide(channelNamed(1, 'Overtime', 'Overtime.us@SD'), ['uk' => ['Overtime.uk' => ['Overtime']]]))->toBeNull()
        ->and(chosenGuide(channelNamed(1, 'Overtime', 'Overtime.us@SD'), ['uk' => ['Overtime.uk' => ['Overtime']]], anyCountry: ['uk']))
        ->toBe(['source' => 'uk', 'channel' => 'Overtime.uk']);
});

it('ignores name matches without a real schedule', function (): void {
    $guides = ['us' => ['UFC.us' => ['UFC']]];

    // Одна передача на весь день — заглушка, а не программа.
    expect(chosenGuide(channelNamed(1, 'UFC HD'), $guides, ['us' => ['UFC.us' => ['programmes' => 6, 'titles' => 1]]]))->toBeNull()
        ->and(chosenGuide(channelNamed(1, 'UFC HD'), $guides))->toBe(['source' => 'us', 'channel' => 'UFC.us']);
});

it('prefers the exact tvg-id over a name match', function (): void {
    $guides = ['plex' => ['plex.tv.Sport.plex' => ['FOX Sports']], 'us' => ['FOXSports.us' => ['FOX Sports 1']]];

    expect(chosenGuide(channelNamed(1, 'FOX Sports', 'FOXSports.us@SD'), $guides, anyCountry: ['plex']))->toBe(['source' => 'us', 'channel' => 'FOXSports.us']);
});

it('matches by name in a guide whose ids carry no country', function (): void {
    // EPG One: id — число, страну по нему не узнать.
    expect(chosenGuide(channelNamed(1, 'QazSport', 'QazSport.kz@SD'), ['epg_one' => ['7509' => ['QazSport']]]))
        ->toBe(['source' => 'epg_one', 'channel' => '7509']);
});

it('keeps the country written in guide names', function (): void {
    $guides = ['epg_one' => ['2720' => ['SPORT 4 HD IL', 'Sport 4 HD IL']]];

    expect(chosenGuide(channelNamed(1, 'Sport 4 (Israel)', 'Sport4.il@SD'), $guides))->toBe(['source' => 'epg_one', 'channel' => '2720'])
        ->and(chosenGuide(channelNamed(1, 'Sport 4 (Ukraine)', 'Sport4.ua@SD'), $guides))->toBeNull();
});

it('ignores spaces and a country code appended to guide names', function (string $name, string $guideName): void {
    expect(chosenGuide(channelNamed(1, $name, 'X.es@SD'), ['epg_one' => ['11414' => [$guideName]]]))->not->toBeNull();
})->with([
    ['Teledeporte', 'Teledeporte HD ES'],
    ['DiviSport', 'Divi Sport HD'],
    ['Fast&FunBox (Netherlands)', 'Fast & Fun Box HD'],
]);

it('does not give a language version the guide of the main channel', function (): void {
    $guides = ['plex' => ['plex.tv.FIFA+.plex' => ['FIFA+']]];

    expect(chosenGuide(channelNamed(1, 'FIFA+ Spain', 'FIFAPlus.uk@Spain'), $guides))->toBeNull()
        ->and(chosenGuide(channelNamed(1, 'FIFA+', 'FIFAPlus.uk@SD'), $guides))->toBe(['source' => 'plex', 'channel' => 'plex.tv.FIFA+.plex']);
});
