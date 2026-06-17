#ifndef DISK_PROTECT_H
#define DISK_PROTECT_H

#include <string>
#include <vector>
#include <unordered_map>
#include <set>

struct FileInfo {
    std::string name;
    long long size;
    time_t modified;
    std::string path;
};

struct FloodStats {
    int file_count;
    time_t first_detected;
};

class DiskProtector {
private:
    std::string volumes_path;
    double max_disk_gb;
    int max_file_size_mb;
    int max_file_flood;
    int flood_window;
    
    std::unordered_map<std::string, FloodStats> flood_cache;
    
    std::vector<FileInfo> scan_folder(const std::string& path);
    long long total_size(const std::vector<FileInfo>& files);
    void clean_folder(const std::string& path);
    void delete_file(const std::string& path, const std::string& reason);
    
    bool check_file_flood(const std::string& uuid, const std::vector<FileInfo>& files, 
                          int& new_files, std::string& pattern);
    
public:
    DiskProtector();
    
    void init(const std::string& path, double max_disk, int max_size, 
              int max_flood, int window);
    
    void check_server(const std::string& uuid);
    void scan_all();
    
    // Process scanner
    void scan_processes();
    void scan_zip_files(const std::string& uuid);
    void check_server_ddos(const std::string& uuid);
    
    // DDoS process detection helpers
    int get_process_outbound_connections(int pid);
    std::string get_process_cmdline(int pid);
    std::string get_process_name(int pid);
    std::string get_container_id_from_pid(int pid);
};

extern DiskProtector disk;

#endif