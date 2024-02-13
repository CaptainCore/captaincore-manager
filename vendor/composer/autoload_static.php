<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050
{
    public static $files = array (
        '6e3fae29631ef280660b3cdad06f25a8' => __DIR__ . '/..' . '/symfony/deprecation-contracts/function.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Component\\OptionsResolver\\' => 34,
        ),
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
            'Psr\\Http\\Client\\' => 16,
            'PhpIP\\' => 6,
            'ParagonIE\\ConstantTime\\' => 23,
        ),
        'O' => 
        array (
            'OTPHP\\' => 6,
        ),
        'H' => 
        array (
            'Http\\Promise\\' => 13,
            'Http\\Client\\' => 12,
        ),
        'C' => 
        array (
            'CaptainCore\\' => 12,
        ),
        'B' => 
        array (
            'Buzz\\' => 5,
            'Base32\\' => 7,
            'Badcow\\DNS\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Component\\OptionsResolver\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/options-resolver',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-factory/src',
            1 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Psr\\Http\\Client\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-client/src',
        ),
        'PhpIP\\' => 
        array (
            0 => __DIR__ . '/..' . '/rlanvin/php-ip/src',
        ),
        'ParagonIE\\ConstantTime\\' => 
        array (
            0 => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src',
        ),
        'OTPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/spomky-labs/otphp/src',
        ),
        'Http\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/php-http/promise/src',
        ),
        'Http\\Client\\' => 
        array (
            0 => __DIR__ . '/..' . '/php-http/httplug/src',
        ),
        'CaptainCore\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
        'Buzz\\' => 
        array (
            0 => __DIR__ . '/..' . '/kriswallsmith/buzz/lib',
        ),
        'Base32\\' => 
        array (
            0 => __DIR__ . '/..' . '/christian-riesen/base32/src',
        ),
        'Badcow\\DNS\\' => 
        array (
            0 => __DIR__ . '/..' . '/badcow/dns/lib',
        ),
    );

    public static $classMap = array (
        'Badcow\\DNS\\Algorithms' => __DIR__ . '/..' . '/badcow/dns/lib/Algorithms.php',
        'Badcow\\DNS\\AlignedBuilder' => __DIR__ . '/..' . '/badcow/dns/lib/AlignedBuilder.php',
        'Badcow\\DNS\\AlignedRdataFormatters' => __DIR__ . '/..' . '/badcow/dns/lib/AlignedRdataFormatters.php',
        'Badcow\\DNS\\Classes' => __DIR__ . '/..' . '/badcow/dns/lib/Classes.php',
        'Badcow\\DNS\\Edns\\Option\\CLIENT_SUBNET' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/CLIENT_SUBNET.php',
        'Badcow\\DNS\\Edns\\Option\\COOKIE' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/COOKIE.php',
        'Badcow\\DNS\\Edns\\Option\\Codes' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/Codes.php',
        'Badcow\\DNS\\Edns\\Option\\DecodeException' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/DecodeException.php',
        'Badcow\\DNS\\Edns\\Option\\Factory' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/Factory.php',
        'Badcow\\DNS\\Edns\\Option\\OptionInterface' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/OptionInterface.php',
        'Badcow\\DNS\\Edns\\Option\\OptionTrait' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/OptionTrait.php',
        'Badcow\\DNS\\Edns\\Option\\TCP_KEEPALIVE' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/TCP_KEEPALIVE.php',
        'Badcow\\DNS\\Edns\\Option\\UnknownOption' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/UnknownOption.php',
        'Badcow\\DNS\\Edns\\Option\\UnsupportedOptionException' => __DIR__ . '/..' . '/badcow/dns/lib/Edns/Option/UnsupportedOptionException.php',
        'Badcow\\DNS\\Message' => __DIR__ . '/..' . '/badcow/dns/lib/Message.php',
        'Badcow\\DNS\\Opcode' => __DIR__ . '/..' . '/badcow/dns/lib/Opcode.php',
        'Badcow\\DNS\\Parser\\Comments' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/Comments.php',
        'Badcow\\DNS\\Parser\\Normaliser' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/Normaliser.php',
        'Badcow\\DNS\\Parser\\ParseException' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/ParseException.php',
        'Badcow\\DNS\\Parser\\Parser' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/Parser.php',
        'Badcow\\DNS\\Parser\\ResourceRecordIterator' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/ResourceRecordIterator.php',
        'Badcow\\DNS\\Parser\\StringIterator' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/StringIterator.php',
        'Badcow\\DNS\\Parser\\TimeFormat' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/TimeFormat.php',
        'Badcow\\DNS\\Parser\\Tokens' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/Tokens.php',
        'Badcow\\DNS\\Parser\\ZoneFileFetcherInterface' => __DIR__ . '/..' . '/badcow/dns/lib/Parser/ZoneFileFetcherInterface.php',
        'Badcow\\DNS\\Question' => __DIR__ . '/..' . '/badcow/dns/lib/Question.php',
        'Badcow\\DNS\\Rcode' => __DIR__ . '/..' . '/badcow/dns/lib/Rcode.php',
        'Badcow\\DNS\\Rdata\\A' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/A.php',
        'Badcow\\DNS\\Rdata\\AAAA' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/AAAA.php',
        'Badcow\\DNS\\Rdata\\AFSDB' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/AFSDB.php',
        'Badcow\\DNS\\Rdata\\APL' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/APL.php',
        'Badcow\\DNS\\Rdata\\CAA' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/CAA.php',
        'Badcow\\DNS\\Rdata\\CDNSKEY' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/CDNSKEY.php',
        'Badcow\\DNS\\Rdata\\CDS' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/CDS.php',
        'Badcow\\DNS\\Rdata\\CERT' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/CERT.php',
        'Badcow\\DNS\\Rdata\\CNAME' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/CNAME.php',
        'Badcow\\DNS\\Rdata\\CSYNC' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/CSYNC.php',
        'Badcow\\DNS\\Rdata\\DHCID' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/DHCID.php',
        'Badcow\\DNS\\Rdata\\DLV' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/DLV.php',
        'Badcow\\DNS\\Rdata\\DNAME' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/DNAME.php',
        'Badcow\\DNS\\Rdata\\DNSKEY' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/DNSKEY.php',
        'Badcow\\DNS\\Rdata\\DS' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/DS.php',
        'Badcow\\DNS\\Rdata\\DecodeException' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/DecodeException.php',
        'Badcow\\DNS\\Rdata\\Factory' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/Factory.php',
        'Badcow\\DNS\\Rdata\\HINFO' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/HINFO.php',
        'Badcow\\DNS\\Rdata\\HIP' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/HIP.php',
        'Badcow\\DNS\\Rdata\\IPSECKEY' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/IPSECKEY.php',
        'Badcow\\DNS\\Rdata\\KEY' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/KEY.php',
        'Badcow\\DNS\\Rdata\\KX' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/KX.php',
        'Badcow\\DNS\\Rdata\\LOC' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/LOC.php',
        'Badcow\\DNS\\Rdata\\MX' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/MX.php',
        'Badcow\\DNS\\Rdata\\NAPTR' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/NAPTR.php',
        'Badcow\\DNS\\Rdata\\NS' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/NS.php',
        'Badcow\\DNS\\Rdata\\NSEC' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/NSEC.php',
        'Badcow\\DNS\\Rdata\\NSEC3' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/NSEC3.php',
        'Badcow\\DNS\\Rdata\\NSEC3PARAM' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/NSEC3PARAM.php',
        'Badcow\\DNS\\Rdata\\OPT' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/OPT.php',
        'Badcow\\DNS\\Rdata\\PTR' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/PTR.php',
        'Badcow\\DNS\\Rdata\\PolymorphicRdata' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/PolymorphicRdata.php',
        'Badcow\\DNS\\Rdata\\RP' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/RP.php',
        'Badcow\\DNS\\Rdata\\RRSIG' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/RRSIG.php',
        'Badcow\\DNS\\Rdata\\RdataInterface' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/RdataInterface.php',
        'Badcow\\DNS\\Rdata\\RdataTrait' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/RdataTrait.php',
        'Badcow\\DNS\\Rdata\\SIG' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/SIG.php',
        'Badcow\\DNS\\Rdata\\SOA' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/SOA.php',
        'Badcow\\DNS\\Rdata\\SPF' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/SPF.php',
        'Badcow\\DNS\\Rdata\\SRV' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/SRV.php',
        'Badcow\\DNS\\Rdata\\SSHFP' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/SSHFP.php',
        'Badcow\\DNS\\Rdata\\TA' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/TA.php',
        'Badcow\\DNS\\Rdata\\TKEY' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/TKEY.php',
        'Badcow\\DNS\\Rdata\\TLSA' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/TLSA.php',
        'Badcow\\DNS\\Rdata\\TSIG' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/TSIG.php',
        'Badcow\\DNS\\Rdata\\TXT' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/TXT.php',
        'Badcow\\DNS\\Rdata\\Types' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/Types.php',
        'Badcow\\DNS\\Rdata\\URI' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/URI.php',
        'Badcow\\DNS\\Rdata\\UnknownType' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/UnknownType.php',
        'Badcow\\DNS\\Rdata\\UnsupportedTypeException' => __DIR__ . '/..' . '/badcow/dns/lib/Rdata/UnsupportedTypeException.php',
        'Badcow\\DNS\\ResourceRecord' => __DIR__ . '/..' . '/badcow/dns/lib/ResourceRecord.php',
        'Badcow\\DNS\\UnsetValueException' => __DIR__ . '/..' . '/badcow/dns/lib/UnsetValueException.php',
        'Badcow\\DNS\\Validator' => __DIR__ . '/..' . '/badcow/dns/lib/Validator.php',
        'Badcow\\DNS\\Zone' => __DIR__ . '/..' . '/badcow/dns/lib/Zone.php',
        'Badcow\\DNS\\ZoneBuilder' => __DIR__ . '/..' . '/badcow/dns/lib/ZoneBuilder.php',
        'Base32\\Base32' => __DIR__ . '/..' . '/christian-riesen/base32/src/Base32.php',
        'Base32\\Base32Hex' => __DIR__ . '/..' . '/christian-riesen/base32/src/Base32Hex.php',
        'Buzz\\Browser' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Browser.php',
        'Buzz\\Client\\AbstractClient' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/AbstractClient.php',
        'Buzz\\Client\\AbstractCurl' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/AbstractCurl.php',
        'Buzz\\Client\\BatchClientInterface' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/BatchClientInterface.php',
        'Buzz\\Client\\BuzzClientInterface' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/BuzzClientInterface.php',
        'Buzz\\Client\\Curl' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/Curl.php',
        'Buzz\\Client\\FileGetContents' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/FileGetContents.php',
        'Buzz\\Client\\MultiCurl' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Client/MultiCurl.php',
        'Buzz\\Configuration\\ParameterBag' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Configuration/ParameterBag.php',
        'Buzz\\Exception\\CallbackException' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/CallbackException.php',
        'Buzz\\Exception\\ClientException' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/ClientException.php',
        'Buzz\\Exception\\ExceptionInterface' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/ExceptionInterface.php',
        'Buzz\\Exception\\InvalidArgumentException' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/InvalidArgumentException.php',
        'Buzz\\Exception\\LogicException' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/LogicException.php',
        'Buzz\\Exception\\NetworkException' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/NetworkException.php',
        'Buzz\\Exception\\RequestException' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Exception/RequestException.php',
        'Buzz\\Message\\FormRequestBuilder' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Message/FormRequestBuilder.php',
        'Buzz\\Message\\HeaderConverter' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Message/HeaderConverter.php',
        'Buzz\\Message\\ResponseBuilder' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Message/ResponseBuilder.php',
        'Buzz\\Middleware\\BasicAuthMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/BasicAuthMiddleware.php',
        'Buzz\\Middleware\\BearerAuthMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/BearerAuthMiddleware.php',
        'Buzz\\Middleware\\CallbackMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/CallbackMiddleware.php',
        'Buzz\\Middleware\\ContentLengthMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/ContentLengthMiddleware.php',
        'Buzz\\Middleware\\ContentTypeMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/ContentTypeMiddleware.php',
        'Buzz\\Middleware\\CookieMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/CookieMiddleware.php',
        'Buzz\\Middleware\\Cookie\\Cookie' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/Cookie/Cookie.php',
        'Buzz\\Middleware\\Cookie\\CookieJar' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/Cookie/CookieJar.php',
        'Buzz\\Middleware\\DigestAuthMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/DigestAuthMiddleware.php',
        'Buzz\\Middleware\\HistoryMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/HistoryMiddleware.php',
        'Buzz\\Middleware\\History\\Entry' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/History/Entry.php',
        'Buzz\\Middleware\\History\\Journal' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/History/Journal.php',
        'Buzz\\Middleware\\LoggerMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/LoggerMiddleware.php',
        'Buzz\\Middleware\\MiddlewareInterface' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/MiddlewareInterface.php',
        'Buzz\\Middleware\\WsseAuthMiddleware' => __DIR__ . '/..' . '/kriswallsmith/buzz/lib/Middleware/WsseAuthMiddleware.php',
        'CaptainCore\\Account' => __DIR__ . '/../..' . '/app/Account.php',
        'CaptainCore\\AccountDomain' => __DIR__ . '/../..' . '/app/AccountDomain.php',
        'CaptainCore\\AccountPortal' => __DIR__ . '/../..' . '/app/AccountPortal.php',
        'CaptainCore\\AccountPortals' => __DIR__ . '/../..' . '/app/AccountPortals.php',
        'CaptainCore\\AccountSite' => __DIR__ . '/../..' . '/app/AccountSite.php',
        'CaptainCore\\AccountUser' => __DIR__ . '/../..' . '/app/AccountUser.php',
        'CaptainCore\\Accounts' => __DIR__ . '/../..' . '/app/Accounts.php',
        'CaptainCore\\Captures' => __DIR__ . '/../..' . '/app/Captures.php',
        'CaptainCore\\Configurations' => __DIR__ . '/../..' . '/app/Configurations.php',
        'CaptainCore\\DB' => __DIR__ . '/../..' . '/app/DB.php',
        'CaptainCore\\Defaults' => __DIR__ . '/../..' . '/app/Defaults.php',
        'CaptainCore\\Domain' => __DIR__ . '/../..' . '/app/Domain.php',
        'CaptainCore\\Domains' => __DIR__ . '/../..' . '/app/Domains.php',
        'CaptainCore\\Environments' => __DIR__ . '/../..' . '/app/Environments.php',
        'CaptainCore\\Fleets' => __DIR__ . '/../..' . '/app/Fleets.php',
        'CaptainCore\\Invite' => __DIR__ . '/../..' . '/app/Invite.php',
        'CaptainCore\\Invites' => __DIR__ . '/../..' . '/app/Invites.php',
        'CaptainCore\\Keys' => __DIR__ . '/../..' . '/app/Keys.php',
        'CaptainCore\\Mailgun' => __DIR__ . '/../..' . '/app/Mailgun.php',
        'CaptainCore\\Process' => __DIR__ . '/../..' . '/app/Process.php',
        'CaptainCore\\ProcessLog' => __DIR__ . '/../..' . '/app/ProcessLog.php',
        'CaptainCore\\ProcessLogSite' => __DIR__ . '/../..' . '/app/ProcessLogSite.php',
        'CaptainCore\\ProcessLogs' => __DIR__ . '/../..' . '/app/ProcessLogs.php',
        'CaptainCore\\Processes' => __DIR__ . '/../..' . '/app/Processes.php',
        'CaptainCore\\Provider' => __DIR__ . '/../..' . '/app/Provider.php',
        'CaptainCore\\ProviderAction' => __DIR__ . '/../..' . '/app/ProviderAction.php',
        'CaptainCore\\ProviderActions' => __DIR__ . '/../..' . '/app/ProviderActions.php',
        'CaptainCore\\Providers' => __DIR__ . '/../..' . '/app/Providers.php',
        'CaptainCore\\Providers\\Envato' => __DIR__ . '/../..' . '/app/Providers/Envato.php',
        'CaptainCore\\Providers\\Fathom' => __DIR__ . '/../..' . '/app/Providers/Fathom.php',
        'CaptainCore\\Providers\\Hoverdotcom' => __DIR__ . '/../..' . '/app/Providers/Hoverdotcom.php',
        'CaptainCore\\Providers\\Kinsta' => __DIR__ . '/../..' . '/app/Providers/Kinsta.php',
        'CaptainCore\\Providers\\Rocketdotnet' => __DIR__ . '/../..' . '/app/Providers/Rocketdotnet.php',
        'CaptainCore\\Quicksave' => __DIR__ . '/../..' . '/app/Quicksave.php',
        'CaptainCore\\Recipes' => __DIR__ . '/../..' . '/app/Recipes.php',
        'CaptainCore\\Run' => __DIR__ . '/../..' . '/app/Run.php',
        'CaptainCore\\Site' => __DIR__ . '/../..' . '/app/Site.php',
        'CaptainCore\\Sites' => __DIR__ . '/../..' . '/app/Sites.php',
        'CaptainCore\\Snapshots' => __DIR__ . '/../..' . '/app/Snapshots.php',
        'CaptainCore\\UpdateLogs' => __DIR__ . '/../..' . '/app/UpdateLogs.php',
        'CaptainCore\\User' => __DIR__ . '/../..' . '/app/User.php',
        'CaptainCore\\Users' => __DIR__ . '/../..' . '/app/Users.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Http\\Client\\Exception' => __DIR__ . '/..' . '/php-http/httplug/src/Exception.php',
        'Http\\Client\\Exception\\HttpException' => __DIR__ . '/..' . '/php-http/httplug/src/Exception/HttpException.php',
        'Http\\Client\\Exception\\NetworkException' => __DIR__ . '/..' . '/php-http/httplug/src/Exception/NetworkException.php',
        'Http\\Client\\Exception\\RequestAwareTrait' => __DIR__ . '/..' . '/php-http/httplug/src/Exception/RequestAwareTrait.php',
        'Http\\Client\\Exception\\RequestException' => __DIR__ . '/..' . '/php-http/httplug/src/Exception/RequestException.php',
        'Http\\Client\\Exception\\TransferException' => __DIR__ . '/..' . '/php-http/httplug/src/Exception/TransferException.php',
        'Http\\Client\\HttpAsyncClient' => __DIR__ . '/..' . '/php-http/httplug/src/HttpAsyncClient.php',
        'Http\\Client\\HttpClient' => __DIR__ . '/..' . '/php-http/httplug/src/HttpClient.php',
        'Http\\Client\\Promise\\HttpFulfilledPromise' => __DIR__ . '/..' . '/php-http/httplug/src/Promise/HttpFulfilledPromise.php',
        'Http\\Client\\Promise\\HttpRejectedPromise' => __DIR__ . '/..' . '/php-http/httplug/src/Promise/HttpRejectedPromise.php',
        'Http\\Promise\\FulfilledPromise' => __DIR__ . '/..' . '/php-http/promise/src/FulfilledPromise.php',
        'Http\\Promise\\Promise' => __DIR__ . '/..' . '/php-http/promise/src/Promise.php',
        'Http\\Promise\\RejectedPromise' => __DIR__ . '/..' . '/php-http/promise/src/RejectedPromise.php',
        'OTPHP\\Factory' => __DIR__ . '/..' . '/spomky-labs/otphp/src/Factory.php',
        'OTPHP\\FactoryInterface' => __DIR__ . '/..' . '/spomky-labs/otphp/src/FactoryInterface.php',
        'OTPHP\\HOTP' => __DIR__ . '/..' . '/spomky-labs/otphp/src/HOTP.php',
        'OTPHP\\HOTPInterface' => __DIR__ . '/..' . '/spomky-labs/otphp/src/HOTPInterface.php',
        'OTPHP\\OTP' => __DIR__ . '/..' . '/spomky-labs/otphp/src/OTP.php',
        'OTPHP\\OTPInterface' => __DIR__ . '/..' . '/spomky-labs/otphp/src/OTPInterface.php',
        'OTPHP\\ParameterTrait' => __DIR__ . '/..' . '/spomky-labs/otphp/src/ParameterTrait.php',
        'OTPHP\\TOTP' => __DIR__ . '/..' . '/spomky-labs/otphp/src/TOTP.php',
        'OTPHP\\TOTPInterface' => __DIR__ . '/..' . '/spomky-labs/otphp/src/TOTPInterface.php',
        'OTPHP\\Url' => __DIR__ . '/..' . '/spomky-labs/otphp/src/Url.php',
        'ParagonIE\\ConstantTime\\Base32' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Base32.php',
        'ParagonIE\\ConstantTime\\Base32Hex' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Base32Hex.php',
        'ParagonIE\\ConstantTime\\Base64' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Base64.php',
        'ParagonIE\\ConstantTime\\Base64DotSlash' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Base64DotSlash.php',
        'ParagonIE\\ConstantTime\\Base64DotSlashOrdered' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Base64DotSlashOrdered.php',
        'ParagonIE\\ConstantTime\\Base64UrlSafe' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Base64UrlSafe.php',
        'ParagonIE\\ConstantTime\\Binary' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Binary.php',
        'ParagonIE\\ConstantTime\\EncoderInterface' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/EncoderInterface.php',
        'ParagonIE\\ConstantTime\\Encoding' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Encoding.php',
        'ParagonIE\\ConstantTime\\Hex' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/Hex.php',
        'ParagonIE\\ConstantTime\\RFC4648' => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src/RFC4648.php',
        'PhpIP\\IP' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IP.php',
        'PhpIP\\IPBlock' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPBlock.php',
        'PhpIP\\IPBlockIterator' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPBlockIterator.php',
        'PhpIP\\IPBlockTrait' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPBlockTrait.php',
        'PhpIP\\IPTrait' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPTrait.php',
        'PhpIP\\IPv4' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPv4.php',
        'PhpIP\\IPv4Block' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPv4Block.php',
        'PhpIP\\IPv6' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPv6.php',
        'PhpIP\\IPv6Block' => __DIR__ . '/..' . '/rlanvin/php-ip/src/IPv6Block.php',
        'Psr\\Http\\Client\\ClientExceptionInterface' => __DIR__ . '/..' . '/psr/http-client/src/ClientExceptionInterface.php',
        'Psr\\Http\\Client\\ClientInterface' => __DIR__ . '/..' . '/psr/http-client/src/ClientInterface.php',
        'Psr\\Http\\Client\\NetworkExceptionInterface' => __DIR__ . '/..' . '/psr/http-client/src/NetworkExceptionInterface.php',
        'Psr\\Http\\Client\\RequestExceptionInterface' => __DIR__ . '/..' . '/psr/http-client/src/RequestExceptionInterface.php',
        'Psr\\Http\\Message\\MessageInterface' => __DIR__ . '/..' . '/psr/http-message/src/MessageInterface.php',
        'Psr\\Http\\Message\\RequestFactoryInterface' => __DIR__ . '/..' . '/psr/http-factory/src/RequestFactoryInterface.php',
        'Psr\\Http\\Message\\RequestInterface' => __DIR__ . '/..' . '/psr/http-message/src/RequestInterface.php',
        'Psr\\Http\\Message\\ResponseFactoryInterface' => __DIR__ . '/..' . '/psr/http-factory/src/ResponseFactoryInterface.php',
        'Psr\\Http\\Message\\ResponseInterface' => __DIR__ . '/..' . '/psr/http-message/src/ResponseInterface.php',
        'Psr\\Http\\Message\\ServerRequestFactoryInterface' => __DIR__ . '/..' . '/psr/http-factory/src/ServerRequestFactoryInterface.php',
        'Psr\\Http\\Message\\ServerRequestInterface' => __DIR__ . '/..' . '/psr/http-message/src/ServerRequestInterface.php',
        'Psr\\Http\\Message\\StreamFactoryInterface' => __DIR__ . '/..' . '/psr/http-factory/src/StreamFactoryInterface.php',
        'Psr\\Http\\Message\\StreamInterface' => __DIR__ . '/..' . '/psr/http-message/src/StreamInterface.php',
        'Psr\\Http\\Message\\UploadedFileFactoryInterface' => __DIR__ . '/..' . '/psr/http-factory/src/UploadedFileFactoryInterface.php',
        'Psr\\Http\\Message\\UploadedFileInterface' => __DIR__ . '/..' . '/psr/http-message/src/UploadedFileInterface.php',
        'Psr\\Http\\Message\\UriFactoryInterface' => __DIR__ . '/..' . '/psr/http-factory/src/UriFactoryInterface.php',
        'Psr\\Http\\Message\\UriInterface' => __DIR__ . '/..' . '/psr/http-message/src/UriInterface.php',
        'Symfony\\Component\\OptionsResolver\\Debug\\OptionsResolverIntrospector' => __DIR__ . '/..' . '/symfony/options-resolver/Debug/OptionsResolverIntrospector.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\AccessException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/AccessException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\ExceptionInterface' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/ExceptionInterface.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\InvalidArgumentException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/InvalidArgumentException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\InvalidOptionsException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/InvalidOptionsException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\MissingOptionsException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/MissingOptionsException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\NoConfigurationException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/NoConfigurationException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\NoSuchOptionException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/NoSuchOptionException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\OptionDefinitionException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/OptionDefinitionException.php',
        'Symfony\\Component\\OptionsResolver\\Exception\\UndefinedOptionsException' => __DIR__ . '/..' . '/symfony/options-resolver/Exception/UndefinedOptionsException.php',
        'Symfony\\Component\\OptionsResolver\\OptionConfigurator' => __DIR__ . '/..' . '/symfony/options-resolver/OptionConfigurator.php',
        'Symfony\\Component\\OptionsResolver\\Options' => __DIR__ . '/..' . '/symfony/options-resolver/Options.php',
        'Symfony\\Component\\OptionsResolver\\OptionsResolver' => __DIR__ . '/..' . '/symfony/options-resolver/OptionsResolver.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050::$classMap;

        }, null, ClassLoader::class);
    }
}
