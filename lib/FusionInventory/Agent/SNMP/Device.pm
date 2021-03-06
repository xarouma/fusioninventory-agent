package FusionInventory::Agent::SNMP::Device;

use strict;
use warnings;

use FusionInventory::Agent::Tools;
use FusionInventory::Agent::Tools::SNMP;
use FusionInventory::Agent::Tools::Network;
use FusionInventory::Agent::SNMP::MibSupport;

# Supported infos are specified here:
# http://fusioninventory.org/documentation/dev/spec/protocol/netdiscovery.html
use constant discovery => [ qw(
        DESCRIPTION FIRMWARE ID IPS LOCATION MAC MEMORY MODEL SNMPHOSTNAME TYPE
        SERIAL UPTIME MANUFACTURER CONTACT AUTHSNMP
    )];
# http://fusioninventory.org/documentation/dev/spec/protocol/netinventory.html
use constant inventory => [ qw(
        INFO PORTS MODEMS FIRMWARES SIMCARDS PAGECOUNTERS CARTRIDGES
    )];

sub new {
    my ($class, %params) = @_;

    my $snmp   = $params{snmp};
    my $logger = $params{logger};

    return unless $snmp;

    my $self = {
        snmp   => $snmp,
        logger => $logger
    };

    bless $self, $class;

    return $self;
}

sub get {
    my ($self, $oid) = @_;

    return unless $self->{snmp} && $oid;

    return $self->{snmp}->get($oid);
}

sub walk {
    my ($self, $oid) = @_;

    return unless $self->{snmp} && $oid;

    return $self->{snmp}->walk($oid);
}

sub loadMibSupport {
    my ($self, $sysobjectid) = @_;

    # list supported mibs regarding sysORID list as this list permits to
    # identify device supported MIBs
    $self->{MIBSUPPORT} = FusionInventory::Agent::SNMP::MibSupport->new(
        sysobjectid  => $sysobjectid,
        device       => $self
    );
}

sub runMibSupport {
    my ($self) = @_;

    return unless $self->{MIBSUPPORT};

    $self->{MIBSUPPORT}->run();
}

sub getSerialByMibSupport {
    my ($self) = @_;

    return unless $self->{MIBSUPPORT};

    return $self->{MIBSUPPORT}->getMethod('getSerial');
}

sub getFirmwareByMibSupport {
    my ($self) = @_;

    return unless $self->{MIBSUPPORT};

    return $self->{MIBSUPPORT}->getMethod('getFirmware');
}

sub getFirmwareDateByMibSupport {
    my ($self) = @_;

    return unless $self->{MIBSUPPORT};

    return $self->{MIBSUPPORT}->getMethod('getFirmwareDate');
}

sub getMacAddressByMibSupport {
    my ($self) = @_;

    return unless $self->{MIBSUPPORT};

    return $self->{MIBSUPPORT}->getMethod('getMacAddress');
}

sub getIpByMibSupport {
    my ($self) = @_;

    return unless $self->{MIBSUPPORT};

    return $self->{MIBSUPPORT}->getMethod('getIp');
}

sub getDiscoveryInfo {
    my ($self) = @_;

    my $info = {};

    # Filter out to only keep discovery infos
    my $infos = discovery;
    foreach my $infokey (@{$infos}) {
        $info->{$infokey} = $self->{$infokey}
            if exists($self->{$infokey});
    }

    return $info;
}

sub getInventory {
    my ($self) = @_;

    my $inventory = {};

    # Filter out to only keep inventory infos
    my $infos = inventory;
    foreach my $infokey (@{$infos}) {
        $inventory->{$infokey} = $self->{$infokey}
            if exists($self->{$infokey});
    }

    return $inventory;
}

sub addModem {
    my ($self, $modem) = @_;

    return unless _cleanHash($modem);

    push @{$self->{MODEMS}}, $modem;
}

sub addFirmware {
    my ($self, $firmware) = @_;

    return unless _cleanHash($firmware);

    push @{$self->{FIRMWARES}}, $firmware;
}

sub addSimcard {
    my ($self, $simcard) = @_;

    return unless _cleanHash($simcard);

    push @{$self->{SIMCARDS}}, $simcard;
}

sub addPort {
    my ($self, %ports) = @_;

    foreach my $port (keys(%ports)) {
        next unless _cleanHash($ports{$port});

        $self->{PORTS}->{PORT}->{$port} = $ports{$port};
    }
}

sub _cleanHash {
    my ($hashref) = @_;

    return unless ref($hashref) eq 'HASH';

    my $keys = 0 ;
    foreach my $key (keys(%{$hashref})) {
        $keys++;
        next if defined($hashref->{$key});
        delete $hashref->{$key};
        $keys--,
    }

    return $keys;
}

sub setSerial {
    my ($self) = @_;

    my $serial =
        # Entity-MIB::entPhysicalSerialNum
        $self->{snmp}->get_first('.1.3.6.1.2.1.47.1.1.1.1.11') ||
        # Printer-MIB::prtGeneralSerialNumber
        $self->{snmp}->get_first('.1.3.6.1.2.1.43.5.1.1.17') ||
        # Try MIB Support mechanism
        $self->getSerialByMibSupport();

    if ( not defined $serial ) {
        # vendor specific OIDs
        my @oids = (
            '.1.3.6.1.4.1.2636.3.1.3.0',             # Juniper-MIB
            '.1.3.6.1.4.1.248.14.1.1.9.1.10.1',      # Hirschman MIB
            '.1.3.6.1.4.1.253.8.53.3.2.1.3.1',       # Xerox-MIB
            '.1.3.6.1.4.1.367.3.2.1.2.1.4.0',        # Ricoh-MIB
            '.1.3.6.1.4.1.641.2.1.2.1.6.1',          # Lexmark-MIB
            '.1.3.6.1.4.1.1602.1.2.1.4.0',           # Canon-MIB
            '.1.3.6.1.4.1.2435.2.3.9.4.2.1.5.5.1.0', # Brother-MIB
            '.1.3.6.1.4.1.318.1.1.4.1.5.0',          # MasterSwitch-MIB
            '.1.3.6.1.4.1.6027.3.8.1.1.5.0',         # F10-C-SERIES-CHASSIS-MIB
            '.1.3.6.1.4.1.6027.3.10.1.2.2.1.12.1',   # FORCE10-SMI
        );
        foreach my $oid (@oids) {
            $serial = $self->get($oid);
            last if $serial;
        }
    }

    return unless $serial;

    $self->{SERIAL} = getCanonicalSerialNumber($serial);
}

sub setFirmware {
    my ($self) = @_;

    my $firmware =
        # entPhysicalSoftwareRev
        $self->{snmp}->get_first('.1.3.6.1.2.1.47.1.1.1.1.10') ||
        # entPhysicalFirmwareRev
        $self->{snmp}->get_first('.1.3.6.1.2.1.47.1.1.1.1.9')  ||
        # firmware from supported mib
        $self->getFirmwareByMibSupport();

    if ( not defined $firmware ) {
        # vendor specific OIDs
        my @oids = (
            '.1.3.6.1.4.1.9.9.25.1.1.1.2.5',         # Cisco / IOS
            '.1.3.6.1.4.1.248.14.1.1.2.0',           # Hirschman MIB
            '.1.3.6.1.4.1.2636.3.40.1.4.1.1.1.5.0',  # Juniper-MIB
        );
        foreach my $oid (@oids) {
            $firmware = $self->get($oid);
            last if defined $firmware;
        }
    }

    return unless defined $firmware;

    # Set device firmware
    $self->{FIRMWARE} = getCanonicalString($firmware);

    # Also add firmware as device FIRMWARES
    $self->addFirmware({
        NAME            => $self->{MODEL} || 'device',
        DESCRIPTION     => 'device firmware',
        TYPE            => 'device',
        DATE            => $self->getFirmwareDateByMibSupport(),
        VERSION         => $self->{FIRMWARE},
        MANUFACTURER    => $self->{MANUFACTURER}
    });
}

sub setMacAddress {
    my ($self) = @_;

    my $address_oid = ".1.3.6.1.2.1.17.1.1.0";
    my $address = getCanonicalMacAddress(
        # use BRIDGE-MIB::dot1dBaseBridgeAddress if available
        $self->get($address_oid) ||
        # Try MIB Support mechanism
        $self->getMacAddressByMibSupport()
    );

    return $self->{MAC} = $address
        if $address && $address =~ /^$mac_address_pattern$/;

    # fallback on ports addresses (IF-MIB::ifPhysAddress) if unique
    my $addresses_oid = ".1.3.6.1.2.1.2.2.1.6";
    my $addresses = $self->walk($addresses_oid);

    # interfaces list with defined ip to use as filter to select shorter mac address list
    my $ips = $self->walk('.1.3.6.1.2.1.4.20.1.2');

    # If peer adress is known, get mac from it
    my $peer = $self->{snmp}->peer_address();
    if ($peer && $ips->{$peer} && $addresses->{$ips->{$peer}}) {
        $address = getCanonicalMacAddress($addresses->{$ips->{$peer}});
        return $self->{MAC} = $address
            if $address && $address =~ /^$mac_address_pattern$/;
    }

    my @all_mac_addresses = ();

    # Try first to obtain shorter mac address list using ip interface list filter
    @all_mac_addresses = grep { defined } map { $addresses->{$_} } values %{$ips}
        if (keys(%{$ips}));

    # Finally get all defined mac adresses if ip filtered related list remains empty
    @all_mac_addresses = grep { defined } values %{$addresses}
        unless @all_mac_addresses;

    my @valid_mac_addresses =
        uniq
        grep { /^$mac_address_pattern$/ }
        grep { $_ ne '00:00:00:00:00:00' }
        grep { $_ }
        map  { getCanonicalMacAddress($_) }
        @all_mac_addresses;

    if (@valid_mac_addresses) {
        return $self->{MAC} = $valid_mac_addresses[0]
            if @valid_mac_addresses == 1;

        # Compute mac addresses as number and sort them
        my %macs = map { $_ => _numericMac($_) } @valid_mac_addresses;
        my @sortedMac = sort { $macs{$a} <=> $macs{$b} } @valid_mac_addresses;

        # Then find first couple of consecutive mac and return first one as this
        # seems to be the first manufacturer defined mac address
        while (@sortedMac > 1) {
            my $currentMac = shift @sortedMac;
            return $self->{MAC} = $currentMac
                if ($macs{$currentMac} == $macs{$sortedMac[0]} - 1);
        }
    }
}

sub _numericMac {
    my ($mac) = @_;

    my $number = 0;
    my $multiplicator = 1;

    my @parts = split(':', $mac);
    while (@parts) {
        $number += hex(pop(@parts))*$multiplicator;
        $multiplicator <<= 8 ;
    }

    return $number;
}


sub setIp {
    my ($self) = @_;

    my $results = $self->walk('.1.3.6.1.2.1.4.20.1.1');
    return $self->{IPS}->{IP} = [
        sort values %{$results}
    ] if $results;

    my $ip = $self->getIpByMibSupport();
    $self->{IPS}->{IP} = [ $ip ] if $ip;
}

1;

__END__

=head1 NAME

FusionInventory::Agent::SNMP::Device - FusionInventory agent SNMP device

=head1 DESCRIPTION

Class to help handle general method to apply on snmp device discovery/inventory

=head1 METHODS

=head2 new(%params)

The constructor. The following parameters are allowed, as keys of the %params
hash:

=over

=item logger

=item snmp (mandatory)  SNMP session object

=back
