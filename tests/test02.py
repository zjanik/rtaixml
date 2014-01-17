import socket
import time
import MySQLdb

def index(req, address="", port=29502, measuredSampleCount=10000, delaySampleCount=1000, db="no", table="data"):
	output = ""
	samples = 1
	
	if db == "yes":
		mysql = MySQLdb.connect("127.0.0.1", "root", "aaa", "rtaixml")
		c = mysql.cursor()
		c.execute("DELETE FROM " + table + " WHERE server = '" + address + "';")
		mysql.commit()

	s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
	s.connect((address, int(port)))

	seqLoop = {}
	seqLoopChangeIndicator = {}
	sampleCounts = []
	totalSampleCount = 0
	delayedSampleCount = 0
	clockStarted = False

	while samples:
		
		if not clockStarted and delayedSampleCount >= delaySampleCount:
			executionTime = -time.time()
			clockStarted = True
		
		samples = s.recv(8096)
		
		samplesList = samples.split("\n")
		
		queryValues = []
		for sample in samplesList[:-1]:
			#create mysql string
			value = sample.split(" ")
			
			if len(value) >= 3 and value[0] != "":
				#set sequence loop default values
				if not int(value[0]) in seqLoop:
					seqLoop[int(value[0])] = 0
					seqLoopChangeIndicator[int(value[0])] = False
				
				if int(value[1]) < 32768:
					if seqLoopChangeIndicator[int(value[0])] == True: #increase sequence loop number after sequence number restart
						seqLoop[int(value[0])] += 1
					seqLoopChangeIndicator[int(value[0])] = False; #we do not need to watch for sequence number restart
				else:
					seqLoopChangeIndicator[int(value[0])] = True; #we need to watch for restart
				
				queryValues.append("('" + address + "', '" + str(value[0]) + "', '" + str(seqLoop[int(value[0])]) + "', '" + str(value[1]) + "','" + str(value[2]) + "')")

		sampleCount = len(queryValues)
		if sampleCount > 0:
			if clockStarted:
				if db == "yes":
					c.execute("INSERT INTO " + table + " (server, signalId, seqLoop, seq, value) VALUES " + ",".join(queryValues) + ";")
					mysql.commit()
				sampleCounts.append(sampleCount)
				totalSampleCount += sampleCount
			else:
				delayedSampleCount += sampleCount
		
		if int(measuredSampleCount) and totalSampleCount >= int(measuredSampleCount):
			break
	
	executionTime += time.time()
	s.close()

	#average sample count in one packet
	count = len(sampleCounts) #total numbers in array
	total = 0
	for value in sampleCounts:
		total = total + value #total value of array numbers
	average = float(total)/float(count) #get average value
	output += "\nAverage sample count in one packet: " + str(average).replace(".", ",") + "\n";
	
	#average packet count in time
	output += "\nAverage packet count in one second: " + str(count/executionTime).replace(".", ",") + "\n\n";

	output += "Sample count: " + str(totalSampleCount) + "\n"
	output += "Execution time: " + str(executionTime).replace(".", ",") + " s\n"

	return output