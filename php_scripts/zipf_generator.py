import numpy as np
import sys
import collections


a=float(sys.argv[1])

f=open("trace.txt","w")
s=np.random.zipf(a,100000)

cnt = collections.defaultdict(int)
for p in s:
        cnt[int(p)] += 1
        f.write(str(p)+" ")

print " for 100000 keys , with skew factor = ",a," no of unique keys = ",len(cnt)
f.close()
