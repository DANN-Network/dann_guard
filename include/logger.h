#ifndef LOGGER_H
#define LOGGER_H

#include <string>
#include <fstream>

enum LogLevel {
    LOG_INFO,
    LOG_WARN,
    LOG_ERROR,
    LOG_DEBUG
};

class Logger {
private:
    std::ofstream log_file;
    std::string log_path;
    LogLevel min_level;
    
    std::string get_timestamp();
    std::string level_to_string(LogLevel level);
    
public:
    Logger();
    ~Logger();
    
    void init(const std::string& path, LogLevel level = LOG_INFO);
    void log(LogLevel level, const std::string& message);
    void info(const std::string& message);
    void warn(const std::string& message);
    void error(const std::string& message);
    void debug(const std::string& message);
};

extern Logger logger;

#endif