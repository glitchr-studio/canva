<?php

namespace Canva\Harness;

use Canva\Client;
use Canva\FileTokenStorage;
use Canva\OAuth;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpClient\HttpClient;

/** The console that exercises the API with the keys in .env: connect, me, designs, export. */
final class Console
{
    public static function create(): Application
    {
        $http = HttpClient::create();
        $storage = new FileTokenStorage('/harness/.canva-token.json');
        $oauth = new OAuth($http, $storage, (string) getenv('CANVA_CLIENT_ID'), (string) getenv('CANVA_CLIENT_SECRET'));
        $client = new Client($http, $oauth);
        $redirect = (string) (getenv('CANVA_REDIRECT_URI') ?: 'http://127.0.0.1:8081/callback');
        $print = static fn (OutputInterface $out, mixed $data) => $out->writeln(json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

        $app = new Application('canva', '1.x');
        $app->addCommand(self::command('connect', 'The URL to open; paste the code of the redirect back', [], function (InputInterface $in, OutputInterface $out) use ($oauth, $redirect, $app) {
            $verifier = OAuth::verifier();
            $out->writeln('Open: '.$oauth->authorizationUrl($redirect, 'harness', $verifier));
            $code = $app->getHelperSet()->get('question')->ask($in, $out, new Question('Paste the "code" parameter of the redirect: '));
            $token = $oauth->exchange(trim((string) $code), $verifier, $redirect);
            $out->writeln('Connected until '.$token->expiresAt->format(\DATE_ATOM).' ('.$token->scope.')');
        }));
        $app->addCommand(self::command('me', 'The connected account', [], fn ($in, $out) => $print($out, $client->me())));
        $app->addCommand(self::command('designs', 'The account\'s designs', [new InputArgument('query', InputArgument::OPTIONAL)], fn ($in, $out) => $print($out, $client->designs($in->getArgument('query')))));
        $app->addCommand(self::command('export', 'A design exported', [new InputArgument('design', InputArgument::REQUIRED), new InputArgument('type', InputArgument::OPTIONAL, '', 'pdf')], fn ($in, $out) => $print($out, $client->exportAndWait($in->getArgument('design'), $in->getArgument('type')))));
        $app->addCommand(self::command('disconnect', 'The token revoked and forgotten', [], function ($in, $out) use ($oauth) { $oauth->disconnect(); $out->writeln('Disconnected.'); }));

        return $app;
    }

    private static function command(string $name, string $description, array $definition, callable $code): Command
    {
        return (new Command($name))->setDescription($description)->setDefinition($definition)->setCode(fn (InputInterface $in, OutputInterface $out) => $code($in, $out) ?? Command::SUCCESS);
    }
}
