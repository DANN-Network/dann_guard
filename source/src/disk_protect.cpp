#include "disk_protect.h"
#include "logger.h"
#include "db_guard.h"
#include "telegram.h"
#include "tracker_db.h"
#include "config.h"

extern Config config;
#include <dirent.h>
#include <sys/stat.h>
#include <unistd.h>
#include <sstream>
#include <algorithm>
#include <cstdlib>
#include <cstring>
#include <iomanip>
#include <fstream>
#include <set>

DiskProtector::DiskProtector() {}

void DiskProtector::init(const std::string& path, double max_disk, int max_size,
                          int max_flood, int window) {
    volumes_path = path;
    max_disk_gb = max_disk;
    max_file_size_mb = max_size;
    max_file_flood = max_flood;
    flood_window = window;
}

std::vector<FileInfo> DiskProtector::scan_folder(const std::string& path) {
    std::vector<FileInfo> files;
    DIR* dir = opendir(path.c_str());
    if (!dir) return files;
    
    struct dirent* entry;
    while ((entry = readdir(dir)) != nullptr) {
        std::string name = entry->d_name;
        if (name == "." || name == "..") continue;
        
        std::string full_path = path + "/" + name;
        struct stat st;
        if (stat(full_path.c_str(), &st) == 0 && S_ISREG(st.st_mode)) {
            FileInfo fi;
            fi.name = name;
            fi.size = st.st_size;
            fi.modified = st.st_mtime;
            fi.path = full_path;
            files.push_back(fi);
        }
    }
    closedir(dir);
    return files;
}

long long DiskProtector::total_size(const std::vector<FileInfo>& files) {
    long long total = 0;
    for (const auto& f : files) total += f.size;
    return total;
}

void DiskProtector::clean_folder(const std::string& path) {
    std::string cmd = "rm -rf " + path + "/* 2>/dev/null";
    system(cmd.c_str());
    logger.info("🧹 Cleaned folder: " + path);
}

void DiskProtector::delete_file(const std::string& path, const std::string& reason) {
    std::string cmd = "rm -f \"" + path + "\" 2>/dev/null";
    system(cmd.c_str());
    logger.info("🗑️ Deleted: " + path.substr(path.find_last_of('/')+1) + " - " + reason);
}

bool DiskProtector::check_file_flood(const std::string& uuid, const std::vector<FileInfo>& files, 
                                      int& new_files, std::string& pattern) {
    time_t now = time(nullptr);
    new_files = 0;
    pattern = "";
    
    for (const auto& f : files) {
        if (now - f.modified < flood_window) {
            new_files++;
            
            if (f.name.find("junk_") != std::string::npos || 
                f.name.find("kill") != std::string::npos ||
                f.name.find("trash") != std::string::npos ||
                f.name.find("temp") != std::string::npos ||
                f.name.find("flood") != std::string::npos) {
                pattern = f.name;
            }
        }
    }
    
    auto it = flood_cache.find(uuid);
    if (it == flood_cache.end()) {
        FloodStats stats;
        stats.file_count = new_files;
        stats.first_detected = now;
        flood_cache[uuid] = stats;
        return false;
    } else {
        it->second.file_count = new_files;
        
        if (now - it->second.first_detected <= flood_window && new_files >= max_file_flood) {
            return true;
        }
        
        if (now - it->second.first_detected > flood_window) {
            it->second.file_count = new_files;
            it->second.first_detected = now;
        }
    }
    
    return false;
}

std::string calculate_file_hash(const std::string& filepath) {
    // Simple hash for tracking (MD5 not implemented here, using filename + size + mtime as simple hash)
    struct stat st;
    if (stat(filepath.c_str(), &st) != 0) return "";
    
    std::ostringstream hash;
    hash << std::hex << st.st_size << "_" << st.st_mtime;
    return hash.str();
}

void DiskProtector::check_server(const std::string& uuid) {
    std::string path = volumes_path + "/" + uuid;
    std::vector<FileInfo> files = scan_folder(path);
    if (files.empty()) return;
    
    long long total_bytes = total_size(files);
    double total_gb = total_bytes / (1024.0 * 1024.0 * 1024.0);
    
    ServerInfo info = db.get_server_info(uuid);
    
    // ==========================================
    // CHECK FILE FLOOD
    // ==========================================
    int new_files = 0;
    std::string pattern;
    bool flood = check_file_flood(uuid, files, new_files, pattern);
    
    if (flood) {
        logger.warn("⚠️ FILE FLOOD: " + uuid.substr(0,8) + " " + std::to_string(new_files) + " files");
        clean_folder(path);
        
        std::string action = "CLEANED";
        
        if (info.id > 0) {
            db.suspend_server(info.id);
            action = "SUSPENDED + CLEANED";
            
            // RECORD VIOLATION TO DATABASE
            tracker_db.record_violation(
                info.id, info.username, 
                info.id, uuid, info.name,
                VIOLATION_FILE_FLOOD,
                "File flood: " + std::to_string(new_files) + " files in " + std::to_string(flood_window) + "s",
                pattern, 0, total_gb, files.size(),
                action, 7  // Severity 7 for flood
            );
            
            // NOTIFY TELEGRAM
            bot.notify_flood(info, new_files, pattern);
        }
        
        // Update daily stats
        tracker_db.update_daily_stats(info.id > 0 ? 1 : 0, files.size(), 0);
        
        return;
    }
    
    // ==========================================
    // CHECK DISK OVER LIMIT
    // ==========================================
    if (total_gb > max_disk_gb) {
        logger.warn("⚠️ DISK OVER LIMIT: " + uuid.substr(0,8) + " " + std::to_string(total_gb) + "GB");
        clean_folder(path);
        
        std::string action = "CLEANED";
        
        if (info.id > 0) {
            db.suspend_server(info.id);
            action = "SUSPENDED + CLEANED";
            
            // RECORD VIOLATION
            tracker_db.record_violation(
                info.id, info.username,
                info.id, uuid, info.name,
                VIOLATION_DISK_OVER,
                "Disk usage: " + std::to_string(total_gb).substr(0,5) + "GB > " + std::to_string((int)max_disk_gb) + "GB",
                "", total_bytes, total_gb, files.size(),
                action, 8  // Severity 8 for disk over limit
            );
            
            // NOTIFY
            bot.notify_disk_over(info, total_gb, files.size());
        }
        
        tracker_db.update_daily_stats(info.id > 0 ? 1 : 0, files.size(), 0);
        return;
    }
    
    // ==========================================
    // CHECK INDIVIDUAL FILES
    // ==========================================
    bool ada_ilegal = false;
    std::string deleted;
    int delete_count = 0;
    
    for (const auto& f : files) {
        std::string alasan;
        
        // Check file size
        if (f.size > max_file_size_mb * 1024LL * 1024LL) {
            alasan = "File > " + std::to_string(max_file_size_mb) + "MB";
        }
        
        // Check filename for illegal keywords
        if (alasan.empty()) {
            std::string lower = f.name;
            std::transform(lower.begin(), lower.end(), lower.begin(), ::tolower);
            
            std::vector<std::string> file_keywords = {
                "scarry", "kill", "killer", "ddos", "flood", "attack",
                "backdoor", "reverse", "shell", "payload", "xmrig",
                "minerd", "cpuminer", "stratum", "junk", "trash"
            };
            
            for (const auto& kw : file_keywords) {
                if (lower.find(kw) != std::string::npos) {
                    alasan = "Filename contains: " + kw;
                    break;
                }
            }
        }
        
        if (!alasan.empty()) {
            // Track file before deleting
            std::string file_hash = calculate_file_hash(f.path);
            if (info.id > 0 && !file_hash.empty()) {
                tracker_db.track_illegal_file(
                    file_hash, f.name, f.path, uuid, info.id, alasan, f.size
                );
            }
            
            delete_file(f.path, alasan);
            ada_ilegal = true;
            delete_count++;
            deleted += "├─ " + f.name + " (" + alasan + ")\n";
        }
    }
    
    if (ada_ilegal && info.id > 0) {
        // RECORD VIOLATION FOR ILLEGAL FILES
        tracker_db.record_violation(
            info.id, info.username,
            info.id, uuid, info.name,
            VIOLATION_ILLEGAL_FILE,
            "Illegal files detected",
            "", total_bytes, total_gb, delete_count,
            "Files deleted", 5  // Severity 5 for illegal files
        );
        
        // NOTIFY
        bot.notify_files_deleted(info, deleted);
        
        // Update daily stats
        tracker_db.update_daily_stats(0, delete_count, 0);
    }
    
    // Update unique users count in daily stats (simplified)
    if (info.id > 0) {
        // This would need a more sophisticated approach to count unique users per day
        // For now, we just record that a user was active
    }
}

void DiskProtector::scan_all() {
    logger.info("🔍 Scanning volumes...");
    
    DIR* dir = opendir(volumes_path.c_str());
    if (!dir) {
        logger.error("❌ Cannot open " + volumes_path);
        return;
    }
    
    struct dirent* entry;
    int total = 0;
    int active_servers = 0;
    
    while ((entry = readdir(dir)) != nullptr) {
        if (strlen(entry->d_name) != 36) continue;
        
        std::string uuid = entry->d_name;
        struct stat st;
        std::string path = volumes_path + "/" + uuid;
        
        if (stat(path.c_str(), &st) == 0 && S_ISDIR(st.st_mode)) {
            check_server(uuid);
            total++;
            
            // Check if server has files (simplified active check)
            DIR* server_dir = opendir(path.c_str());
            if (server_dir) {
                struct dirent* s_entry;
                while ((s_entry = readdir(server_dir)) != nullptr) {
                    std::string name = s_entry->d_name;
                    if (name != "." && name != "..") {
                        active_servers++;
                        break;
                    }
                }
                closedir(server_dir);
            }
        }
    }
    
    closedir(dir);
    logger.info("📊 Scanned: " + std::to_string(total) + " servers, Active: " + std::to_string(active_servers));
    
    // Clean up old records periodically (once per hour)
    static time_t last_cleanup = 0;
    time_t now = time(nullptr);
    if (now - last_cleanup > 3600) {
        tracker_db.cleanup_old_records(30); // Keep 30 days
        last_cleanup = now;
    }
}

// ==========================================
// PROCESS SCANNER — detect & kill DDoS tools
// ==========================================

std::string DiskProtector::get_process_cmdline(int pid) {
    std::ifstream f("/proc/" + std::to_string(pid) + "/cmdline");
    if (!f.is_open()) return "";
    std::string line;
    std::getline(f, line, '\0');
    // Read rest if any (null-separated args)
    std::string rest;
    while (std::getline(f, rest, '\0')) {
        line += " " + rest;
    }
    return line;
}

std::string DiskProtector::get_process_name(int pid) {
    std::ifstream f("/proc/" + std::to_string(pid) + "/status");
    if (!f.is_open()) return "";
    std::string line;
    while (std::getline(f, line)) {
        if (line.substr(0, 5) == "Name:") {
            return line.substr(6);
        }
    }
    return "";
}

std::string DiskProtector::get_container_id_from_pid(int pid) {
    std::ifstream f("/proc/" + std::to_string(pid) + "/cgroup");
    if (!f.is_open()) return "";
    std::string line;
    while (std::getline(f, line)) {
        // Lines look like: 0::/system.slice/docker-<container_id>.scope
        // or 0::/docker/<container_id>
        size_t pos = line.find("docker-");
        if (pos != std::string::npos) {
            std::string cid = line.substr(pos + 7);
            pos = cid.find(".scope");
            if (pos != std::string::npos) cid = cid.substr(0, pos);
            return cid;
        }
        pos = line.find("/docker/");
        if (pos != std::string::npos) {
            return line.substr(pos + 8);
        }
    }
    return "";
}

int DiskProtector::get_process_outbound_connections(int pid) {
    std::ifstream f("/proc/" + std::to_string(pid) + "/net/tcp");
    if (!f.is_open()) return 0;
    
    std::string line;
    int outbound = 0;
    bool first = true;
    while (std::getline(f, line)) {
        if (first) { first = false; continue; }
        
        // Format: sl local_address rem_address st tx_queue rx_queue tr tm->...
        // st = 01 for ESTABLISHED, 02 for SYN_SENT, etc.
        // rem_address is remote IP:port
        std::vector<std::string> parts;
        std::stringstream ss(line);
        std::string part;
        while (ss >> part) parts.push_back(part);
        
        if (parts.size() < 4) continue;
        
        int state = std::stoi(parts[3], nullptr, 16);
        
        // Check if remote address is not local (0.0.0.0)
        std::string rem = parts[2];
        std::string rem_ip = rem.substr(0, rem.find(':'));
        
        // Skip localhost connections
        if (rem_ip == "0100007F" || rem_ip == "00000000" || rem_ip == "01000000") continue;
        
        // Count ESTABLISHED (01) and SYN_SENT (02) outbound connections
        if (state == 0x01 || state == 0x02) {
            outbound++;
        }
    }
    return outbound;
}

void DiskProtector::scan_processes() {
    if (!config.process_scan.enabled) return;
    
    logger.info("🔍 Scanning processes for DDoS tools...");
    
    DIR* proc = opendir("/proc");
    if (!proc) {
        logger.error("❌ Cannot open /proc");
        return;
    }
    
    struct dirent* entry;
    int killed = 0;
    
    while ((entry = readdir(proc)) != nullptr) {
        if (entry->d_type != DT_DIR) continue;
        
        int pid = atoi(entry->d_name);
        if (pid <= 0) continue;
        
        // Skip kernel processes and ourselves
        if (pid == getpid() || pid == 1) continue;
        
        std::string cmdline = get_process_cmdline(pid);
        std::string pname = get_process_name(pid);
        std::string lower_cmd = cmdline;
        std::transform(lower_cmd.begin(), lower_cmd.end(), lower_cmd.begin(), ::tolower);
        std::string lower_name = pname;
        std::transform(lower_name.begin(), lower_name.end(), lower_name.begin(), ::tolower);
        
        // Check against known DDoS keywords
        std::string matched_keyword;
        for (const auto& kw : config.process_scan.keywords) {
            if (lower_cmd.find(kw) != std::string::npos || 
                lower_name.find(kw) != std::string::npos) {
                matched_keyword = kw;
                break;
            }
        }
        
        if (matched_keyword.empty()) continue;
        
        // Also check outbound connections for additional evidence
        int outbound = get_process_outbound_connections(pid);
        
        // Only kill if it has malicious keyword AND suspicious outbound connections
        // OR just the keyword is a high-confidence DDoS tool name
        std::vector<std::string> high_confidence = {
            "child_process", "distress", "mhddos", "goldeneye", "slowloris",
            "hulk", "xerxes", "torshammer", "hitme", "stresser",
            "synflood", "udpflood", "httpflood", "tcpflood"
        };
        
        bool is_high_confidence = false;
        for (const auto& hc : high_confidence) {
            if (lower_cmd.find(hc) != std::string::npos || 
                lower_name.find(hc) != std::string::npos) {
                is_high_confidence = true;
                break;
            }
        }
        
        // For general keywords (ddos, flood, attack), require outbound connections as evidence
        bool should_kill = false;
        if (is_high_confidence) {
            should_kill = true;
        } else if (outbound >= config.process_scan.max_outbound_conns) {
            should_kill = true;
        }
        
        if (!should_kill) {
            // Log suspicious process but don't kill — avoid false positives
            logger.warn("⚠️ Suspicious process (low confidence): " + pname + " (PID " + std::to_string(pid) + ") keyword: " + matched_keyword);
            continue;
        }
        
        // Try to find which server this belongs to
        std::string container_id = get_container_id_from_pid(pid);
        ServerInfo info;
        info.id = -1;
        
        // If it's in a container, try to match to a server UUID
        if (!container_id.empty()) {
            // Wings uses container IDs that don't directly match server UUIDs
            // We need to check /var/lib/pterodactyl/volumes/<uuid>/.<container_id>
            DIR* vol = opendir(volumes_path.c_str());
            if (vol) {
                struct dirent* v_entry;
                while ((v_entry = readdir(vol)) != nullptr) {
                    if (strlen(v_entry->d_name) == 36) {
                        std::string uuid = v_entry->d_name;
                        std::string container_file = volumes_path + "/" + uuid + "/.container-id";
                        std::ifstream cf(container_file);
                        if (cf.is_open()) {
                            std::string cid;
                            std::getline(cf, cid);
                            if (cid == container_id) {
                                info = db.get_server_info(uuid);
                                break;
                            }
                        }
                    }
                }
                closedir(vol);
            }
        }
        
        // Kill the process
        std::string kill_cmd = "kill -9 " + std::to_string(pid) + " 2>/dev/null";
        system(kill_cmd.c_str());
        
        logger.warn("💀 Killed process PID " + std::to_string(pid) + " (" + pname + ") — " + matched_keyword);
        killed++;
        
        // Record violation
        if (info.id > 0) {
            tracker_db.record_violation(
                info.id, info.username,
                info.id, info.uuid, info.name,
                VIOLATION_ILLEGAL_PROCESS,
                "Malicious process: " + pname + " (keyword: " + matched_keyword + ")",
                pname, 0, 0, 0,
                "Process killed", 9
            );
            
            bot.notify_process_killed(info, pid, pname, "DDoS tool detected: " + matched_keyword);
            
            // Also try to find and delete the script file
            if (!cmdline.empty()) {
                std::string script_path;
                // Check if cmdline contains a script file path
                size_t pos = cmdline.find("/");
                if (pos != std::string::npos) {
                    // Extract the script path from cmdline
                    std::string first_arg = cmdline.substr(pos);
                    pos = first_arg.find(" ");
                    if (pos != std::string::npos) first_arg = first_arg.substr(0, pos);
                    if (first_arg.find(volumes_path) != std::string::npos) {
                        script_path = first_arg;
                    }
                }
                if (!script_path.empty() && script_path.find(volumes_path) != std::string::npos) {
                    delete_file(script_path, "DDoS script: " + matched_keyword);
                }
            }
            
            // Suspend the server
            db.suspend_server(info.id);
            logger.warn("⛔ Server " + info.name + " suspended due to DDoS process");
        }
    }
    
    closedir(proc);
    
    if (killed > 0) {
        logger.warn("💀 Total processes killed: " + std::to_string(killed));
        tracker_db.update_daily_stats(0, 0, killed);
    }
}

// ==========================================
// ZIP FILE SCANNER — scan inside archives
// ==========================================

void DiskProtector::scan_zip_files(const std::string& uuid) {
    std::string path = volumes_path + "/" + uuid;
    std::vector<FileInfo> files = scan_folder(path);
    if (files.empty()) return;
    
    for (const auto& f : files) {
        std::string lower = f.name;
        std::transform(lower.begin(), lower.end(), lower.begin(), ::tolower);
        
        if (lower.size() < 4) continue;
        std::string ext = lower.substr(lower.size() - 4);
        
        if (ext != ".zip" && ext != ".tar" && lower.find(".tar.gz") == std::string::npos && 
            lower.find(".tgz") == std::string::npos && lower.find(".rar") == std::string::npos) {
            continue;
        }
        
        logger.info("📦 Scanning archive: " + f.name);
        
        // List contents of the archive
        std::string list_cmd = "unzip -l \"" + f.path + "\" 2>/dev/null | tail -n +4 | head -n -2 | awk '{print $4}'";
        FILE* fp = popen(list_cmd.c_str(), "r");
        if (!fp) continue;
        
        std::vector<std::string> archive_files;
        char buf[4096];
        while (fgets(buf, sizeof(buf), fp)) {
            std::string name = buf;
            while (!name.empty() && (name.back() == '\n' || name.back() == '\r')) name.pop_back();
            if (!name.empty()) archive_files.push_back(name);
        }
        pclose(fp);
        
        if (archive_files.empty()) continue;
        
        bool malicious_zip = false;
        std::string malicious_reason;
        std::string malicious_file_found;
        
        // Check filenames for illegal keywords
        std::vector<std::string> zip_file_keywords = {
            "ddos", "flood", "attack", "stresser", "hitme", "child_process",
            "backdoor", "reverse", "shell", "payload", "xmrig", "minerd",
            "goldeneye", "slowloris", "hulk", "xerxes", "torshammer",
            "synflood", "udpflood", "httpflood", "kill ", "killer", "scarry",
            "junk", "trash"
        };
        
        for (const auto& af : archive_files) {
            std::string af_lower = af;
            std::transform(af_lower.begin(), af_lower.end(), af_lower.begin(), ::tolower);
            
            for (const auto& kw : zip_file_keywords) {
                if (af_lower.find(kw) != std::string::npos) {
                    malicious_zip = true;
                    malicious_reason = "Contains file: " + af + " (keyword: " + kw + ")";
                    malicious_file_found = af;
                    break;
                }
            }
            if (malicious_zip) break;
            
            // Check extension
            size_t dot = af_lower.rfind('.');
            if (dot != std::string::npos) {
                std::string af_ext = af_lower.substr(dot);
                // For script files inside ZIP, extract and scan content
                if (af_ext == ".php" || af_ext == ".cpp" || af_ext == ".c" || 
                    af_ext == ".h" || af_ext == ".hpp" || af_ext == ".py" ||
                    af_ext == ".pl" || af_ext == ".rb" || af_ext == ".sh" ||
                    af_ext == ".bash" || af_ext == ".js") {
                    
                    // Extract file content and scan for malicious patterns
                    std::string extract_cmd = "unzip -p \"" + f.path + "\" \"" + af + "\" 2>/dev/null";
                    FILE* efp = popen(extract_cmd.c_str(), "r");
                    if (efp) {
                        std::string content;
                        char ebuf[4096];
                        size_t total_read = 0;
                        while (fgets(ebuf, sizeof(ebuf), efp) && total_read < 1048576) {
                            content += ebuf;
                            total_read += strlen(ebuf);
                        }
                        pclose(efp);
                        
                        if (!content.empty()) {
                            std::string content_lower = content;
                            std::transform(content_lower.begin(), content_lower.end(), content_lower.begin(), ::tolower);
                            
                            // Check for malicious code patterns
                            std::vector<std::string> rce_patterns = {
                                "child_process", "exec(", "system(", "passthru(", "shell_exec(",
                                "proc_open(", "popen(", "eval(", "base64_decode(",
                                "fsockopen(", "curl_exec(", "socket_create(",
                                "LD_PRELOAD", "chmod 777", "wget ", "curl ",
                                "gcc ", "g++ ", "perl ", "python ",
                                "sh -c", "bash -c", "/bin/sh", "/bin/bash"
                            };
                            
                            for (const auto& rp : rce_patterns) {
                                if (content_lower.find(rp) != std::string::npos) {
                                    malicious_zip = true;
                                    malicious_reason = "ZIP contains malicious code in " + af + " (pattern: " + rp + ")";
                                    malicious_file_found = af;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (malicious_zip) {
            // Get server info
            ServerInfo info = db.get_server_info(uuid);
            
            // Delete the ZIP file
            delete_file(f.path, "Archive contains malicious content: " + malicious_reason);
            
            if (info.id > 0) {
                tracker_db.record_violation(
                    info.id, info.username,
                    info.id, uuid, info.name,
                    VIOLATION_ILLEGAL_FILE,
                    "Malicious archive: " + f.name + " (" + malicious_reason + ")",
                    malicious_file_found, f.size, 0, 1,
                    "Archive deleted", 8
                );
                
                // Notify
                std::string delete_msg = "├─ " + f.name + " (Malicious archive)\n";
                bot.notify_files_deleted(info, delete_msg);
            }
        }
    }
}

// ==========================================
// ANTI LOCAL DDoS — detect outbound attacks
// ==========================================

void DiskProtector::check_server_ddos(const std::string& uuid) {
    if (!config.anti_ddos.enabled) return;
    
    std::string path = volumes_path + "/" + uuid;
    std::vector<FileInfo> files = scan_folder(path);
    if (files.empty()) return;
    
    // Count outbound connections from processes related to this UUID
    int total_outbound = 0;
    int suspicious_pids = 0;
    DIR* proc = opendir("/proc");
    if (!proc) return;
    
    // First get container ID for this server UUID
    std::string container_id_file = path + "/.container-id";
    std::string target_container_id;
    std::ifstream cf(container_id_file);
    if (cf.is_open()) std::getline(cf, target_container_id);
    cf.close();
    
    if (target_container_id.empty()) {
        closedir(proc);
        return;
    }
    
    struct dirent* entry;
    std::set<int> checked_pids;
    
    while ((entry = readdir(proc)) != nullptr) {
        if (entry->d_type != DT_DIR) continue;
        int pid = atoi(entry->d_name);
        if (pid <= 0 || pid == getpid() || pid == 1) continue;
        
        std::string cid = get_container_id_from_pid(pid);
        if (cid != target_container_id) continue;
        
        int conns = get_process_outbound_connections(pid);
        total_outbound += conns;
        if (conns > 10) suspicious_pids++;
        checked_pids.insert(pid);
    }
    closedir(proc);
    
    if (total_outbound > config.anti_ddos.max_outbound) {
        logger.warn("⚠️ DDoS DETECTED: " + uuid.substr(0,8) + " — " + std::to_string(total_outbound) + " outbound connections from " + std::to_string(checked_pids.size()) + " processes");
        
        ServerInfo info = db.get_server_info(uuid);
        if (info.id > 0) {
            db.suspend_server(info.id);
            
            tracker_db.record_violation(
                info.id, info.username,
                info.id, uuid, info.name,
                VIOLATION_CPU_ABUSE,
                "Potential DDoS: " + std::to_string(total_outbound) + " outbound connections",
                "", 0, 0, suspicious_pids,
                "Server suspended", 10
            );
            
            bot.notify_suspend(info, "Local DDoS detected",
                "├─ Outbound: " + std::to_string(total_outbound) + " conns\n├─ Processes: " + std::to_string(checked_pids.size()),
                "Server suspended");
        }
    }
}

DiskProtector disk;